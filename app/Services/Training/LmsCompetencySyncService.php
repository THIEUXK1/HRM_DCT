<?php

namespace App\Services\Training;

use App\Models\CourseCompetency;
use App\Models\EmployeeCompetencyAssessment;
use App\Models\TrainingEnrollment;

class LmsCompetencySyncService
{
    /**
     * @return array<int, array{competency_id: int, competency_name: string, level: int, action: string}>
     */
    public function syncFromCompletedEnrollment(TrainingEnrollment $enrollment): array
    {
        $enrollment->loadMissing('trainingClass.course', 'employee');
        $course = $enrollment->trainingClass?->course;
        if (! $course) {
            return [];
        }

        $score = (float) ($enrollment->score ?? 100);
        $mappings = CourseCompetency::query()
            ->where('course_id', $course->id)
            ->with('competency')
            ->get();

        $updates = [];
        foreach ($mappings as $mapping) {
            if ($score < (float) $mapping->min_score) {
                continue;
            }

            $existing = EmployeeCompetencyAssessment::query()
                ->where('employee_id', $enrollment->employee_id)
                ->where('competency_id', $mapping->competency_id)
                ->first();

            $newLevel = max(
                $existing?->current_level ?? 0,
                (int) $mapping->granted_level
            );

            EmployeeCompetencyAssessment::updateOrCreate(
                [
                    'employee_id' => $enrollment->employee_id,
                    'competency_id' => $mapping->competency_id,
                ],
                [
                    'current_level' => $newLevel,
                    'assessed_at' => now()->toDateString(),
                    'assessed_by' => auth()->id(),
                    'source' => 'lms',
                    'course_id' => $course->id,
                ]
            );

            $updates[] = [
                'competency_id' => $mapping->competency_id,
                'competency_name' => $mapping->competency?->name,
                'level' => $newLevel,
                'action' => $existing ? 'updated' : 'created',
            ];
        }

        return $updates;
    }
}

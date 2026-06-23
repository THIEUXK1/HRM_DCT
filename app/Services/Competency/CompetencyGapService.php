<?php

namespace App\Services\Competency;

use App\Models\Competency;
use App\Models\Employee;
use App\Models\EmployeeCompetencyAssessment;
use App\Models\PositionCompetencyRequirement;
use Illuminate\Support\Collection;

class CompetencyGapService
{
    public function requirementsForPosition(?int $positionId): Collection
    {
        if (! $positionId) {
            return collect();
        }

        return PositionCompetencyRequirement::query()
            ->where('position_id', $positionId)
            ->with('competency.group')
            ->get();
    }

    /**
     * @return array{employee: array, items: array<int, array>, summary: array}
     */
    public function matrixForEmployee(Employee $employee): array
    {
        $employee->loadMissing(['position', 'department']);

        $requirements = $this->requirementsForPosition($employee->position_id);
        $assessments = EmployeeCompetencyAssessment::query()
            ->where('employee_id', $employee->id)
            ->get()
            ->keyBy('competency_id');

        $competencyIds = $requirements->pluck('competency_id')
            ->merge($assessments->keys())
            ->unique()
            ->values();

        $competencies = Competency::with('group')
            ->whereIn('id', $competencyIds)
            ->get()
            ->keyBy('id');

        $items = [];
        $met = 0;
        $gaps = 0;
        $notAssessed = 0;

        foreach ($competencyIds as $competencyId) {
            $competency = $competencies->get($competencyId);
            if (! $competency) {
                continue;
            }

            $required = $requirements->firstWhere('competency_id', $competencyId);
            $assessment = $assessments->get($competencyId);
            $requiredLevel = $required?->required_level;
            $currentLevel = $assessment?->current_level;
            $gap = $requiredLevel !== null && $currentLevel !== null
                ? max(0, $requiredLevel - $currentLevel)
                : null;

            $gapStatus = $this->gapStatus($requiredLevel, $currentLevel);

            if ($gapStatus === 'met') {
                $met++;
            } elseif ($gapStatus === 'gap') {
                $gaps++;
            } elseif ($gapStatus === 'not_assessed') {
                $notAssessed++;
            }

            $items[] = [
                'competency_id' => $competency->id,
                'competency' => $competency,
                'required_level' => $requiredLevel,
                'current_level' => $currentLevel,
                'gap' => $gap,
                'gap_status' => $gapStatus,
                'assessed_at' => $assessment?->assessed_at?->toDateString(),
            ];
        }

        return [
            'employee' => $employee->only(['id', 'full_name', 'employee_code', 'position_id']),
            'employee_position' => $employee->position,
            'items' => $items,
            'summary' => [
                'total' => count($items),
                'met' => $met,
                'gaps' => $gaps,
                'not_assessed' => $notAssessed,
                'coverage_percent' => count($items) > 0
                    ? (int) round(($met / count($items)) * 100)
                    : 0,
            ],
        ];
    }

    public function gapStatus(?int $required, ?int $current): string
    {
        if ($required === null) {
            return $current !== null ? 'met' : 'not_assessed';
        }

        if ($current === null) {
            return 'not_assessed';
        }

        if ($current >= $required) {
            return 'met';
        }

        if ($current === $required - 1) {
            return 'partial';
        }

        return 'gap';
    }
}

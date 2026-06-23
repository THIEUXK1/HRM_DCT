<?php

namespace App\Http\Controllers\Api;

use App\Models\TrainingClass;
use App\Models\TrainingEnrollment;
use App\Services\Training\LmsCompetencySyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrainingClassController extends ApiController
{
    public function __construct(
        private readonly LmsCompetencySyncService $lmsCompetencySync,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $class = TrainingClass::create($request->validate([
            'course_id' => 'required|exists:courses,id',
            'name' => 'required|string|max:255',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'location' => 'nullable|string',
        ]));

        return $this->success($class->load('course'), 201);
    }

    public function enroll(Request $request, TrainingClass $trainingClass): JsonResponse
    {
        $data = $request->validate([
            'employee_id' => 'required|exists:employees,id',
        ]);

        $enrollment = TrainingEnrollment::firstOrCreate(
            ['training_class_id' => $trainingClass->id, 'employee_id' => $data['employee_id']],
            ['status' => 'enrolled']
        );

        return $this->success($enrollment->load('employee'), 201);
    }

    public function complete(Request $request, TrainingEnrollment $trainingEnrollment): JsonResponse
    {
        $data = $request->validate([
            'score' => 'nullable|numeric|min:0|max:100',
        ]);

        $trainingEnrollment->update([
            'status' => 'completed',
            'completed_at' => now(),
            'score' => $data['score'] ?? $trainingEnrollment->score ?? 100,
        ]);

        $competencyUpdates = $this->lmsCompetencySync->syncFromCompletedEnrollment($trainingEnrollment->fresh());

        return $this->success([
            'enrollment' => $trainingEnrollment->load(['employee', 'trainingClass.course']),
            'competency_updates' => $competencyUpdates,
        ]);
    }
}

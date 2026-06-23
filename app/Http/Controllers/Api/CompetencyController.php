<?php

namespace App\Http\Controllers\Api;

use App\Models\Competency;
use App\Models\CompetencyGroup;
use App\Models\Employee;
use App\Models\EmployeeCompetencyAssessment;
use App\Models\Position;
use App\Models\PositionCompetencyRequirement;
use App\Support\CompanyContext;
use App\Services\Competency\CompetencyGapService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompetencyController extends ApiController
{
    public function __construct(
        private readonly CompetencyGapService $gapService,
    ) {}

    public function meta(): JsonResponse
    {
        return $this->success([
            'levels' => config('competency.levels'),
            'gap_statuses' => config('competency.gap_status'),
        ]);
    }

    public function index(): JsonResponse
    {
        return $this->success(
            CompetencyGroup::with('competencies')->get()
        );
    }

    public function assess(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'competency_id' => 'required|exists:competencies,id',
            'current_level' => 'required|integer|min:1|max:5',
            'assessed_at' => 'nullable|date',
        ]);

        $competency = Competency::findOrFail($data['competency_id']);
        $max = $competency->max_level ?: 5;
        if ($data['current_level'] > $max) {
            return $this->error('Mức đánh giá vượt quá max level của năng lực', 422);
        }

        $assessment = EmployeeCompetencyAssessment::updateOrCreate(
            ['employee_id' => $data['employee_id'], 'competency_id' => $data['competency_id']],
            [
                'current_level' => $data['current_level'],
                'assessed_at' => $data['assessed_at'] ?? now()->toDateString(),
                'assessed_by' => auth()->id(),
                'source' => 'manual',
                'course_id' => null,
            ]
        );

        return $this->success($assessment->load(['employee', 'competency']));
    }

    public function employeeMatrix(Employee $employee): JsonResponse
    {
        return $this->success($this->gapService->matrixForEmployee($employee));
    }

    public function positionRequirements(Position $position): JsonResponse
    {
        return $this->success(
            $this->gapService->requirementsForPosition($position->id)
        );
    }

    public function syncPositionRequirements(Request $request, Position $position): JsonResponse
    {
        $data = $request->validate([
            'requirements' => 'required|array',
            'requirements.*.competency_id' => 'required|exists:competencies,id',
            'requirements.*.required_level' => 'required|integer|min:1|max:5',
        ]);

        PositionCompetencyRequirement::query()
            ->where('position_id', $position->id)
            ->delete();

        foreach ($data['requirements'] as $row) {
            PositionCompetencyRequirement::create([
                'position_id' => $position->id,
                'competency_id' => $row['competency_id'],
                'required_level' => $row['required_level'],
            ]);
        }

        return $this->success(
            $this->gapService->requirementsForPosition($position->id)
        );
    }

    public function storeGroup(Request $request): JsonResponse
    {
        $tenantId = CompanyContext::tenantId();
        if (! $tenantId) {
            return $this->error('Thiếu tenant context, vui lòng chọn công ty hợp lệ', 422);
        }

        $group = CompetencyGroup::create([
            'tenant_id' => $tenantId,
            'name' => $request->validate(['name' => 'required|string'])['name'],
        ]);

        return $this->success($group, 201);
    }

    public function storeCompetency(Request $request): JsonResponse
    {
        $competency = Competency::create($request->validate([
            'competency_group_id' => 'required|exists:competency_groups,id',
            'name' => 'required|string',
            'code' => 'required|string',
            'max_level' => 'integer|min:1|max:5',
        ]));

        return $this->success($competency, 201);
    }
}

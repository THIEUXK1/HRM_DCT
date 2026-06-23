<?php

namespace App\Http\Controllers\Api;

use App\Models\EmployeeReview;
use App\Models\Goal;
use App\Models\PerformanceCycle;
use App\Services\Performance\PerformanceScoreService;
use App\Support\CompanyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PerformanceController extends ApiController
{
    public function __construct(
        private readonly PerformanceScoreService $scoreService,
    ) {}

    public function meta(): JsonResponse
    {
        return $this->success([
            'cycle_statuses' => config('performance.cycle_statuses'),
            'goal_statuses' => config('performance.goal_statuses'),
            'review_statuses' => config('performance.review_statuses'),
            'ratings' => config('performance.ratings'),
            'weights' => config('performance.weights'),
        ]);
    }

    public function cycles(): JsonResponse
    {
        $cycles = PerformanceCycle::with(['reviews.employee:id,full_name,employee_code', 'goals.employee:id,full_name,employee_code'])
            ->orderByDesc('period')
            ->limit(24)
            ->get()
            ->map(function (PerformanceCycle $cycle) {
                $cycle->setAttribute('kpi_summary', $this->cycleKpiSummary($cycle));

                return $cycle;
            });

        return $this->success($cycles);
    }

    public function storeCycle(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string',
            'period' => ['required', 'regex:/^\d{4}(-\d{2})?$/'],
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $data['tenant_id'] = CompanyContext::tenantId();
        if (! $data['tenant_id']) {
            return $this->error('Thiếu tenant context, vui lòng chọn công ty hợp lệ', 422);
        }
        $data['status'] = 'active';
        if (strlen($data['period']) === 4) {
            $data['period'] = $data['period'].'-01';
        }

        return $this->success(PerformanceCycle::create($data), 201);
    }

    public function storeGoal(Request $request): JsonResponse
    {
        $goal = Goal::create($request->validate([
            'performance_cycle_id' => 'required|exists:performance_cycles,id',
            'employee_id' => 'required|exists:employees,id',
            'title' => 'required|string',
            'description' => 'nullable|string',
            'target_value' => 'nullable|numeric|min:0',
            'actual_value' => 'nullable|numeric|min:0',
            'weight' => 'nullable|numeric|min:0|max:100',
        ]));

        $goal->setAttribute('progress_percent', $this->scoreService->goalProgress($goal));

        return $this->success($goal->load('employee'), 201);
    }

    public function updateGoal(Request $request, Goal $goal): JsonResponse
    {
        $goal->update($request->validate([
            'title' => 'sometimes|string',
            'description' => 'nullable|string',
            'target_value' => 'nullable|numeric|min:0',
            'actual_value' => 'nullable|numeric|min:0',
            'weight' => 'nullable|numeric|min:0|max:100',
            'status' => 'nullable|in:active,achieved,missed,cancelled',
        ]));

        if ($goal->actual_value !== null && $goal->target_value !== null) {
            $progress = $this->scoreService->goalProgress($goal);
            if ($progress !== null && $progress >= 100 && $goal->status === 'active') {
                $goal->update(['status' => 'achieved']);
            }
        }

        $goal->setAttribute('progress_percent', $this->scoreService->goalProgress($goal->fresh()));

        return $this->success($goal->fresh('employee'));
    }

    public function storeReview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'performance_cycle_id' => 'required|exists:performance_cycles,id',
            'employee_id' => 'required|exists:employees,id',
        ]);

        $review = EmployeeReview::firstOrCreate(
            [
                'performance_cycle_id' => $data['performance_cycle_id'],
                'employee_id' => $data['employee_id'],
            ],
            ['status' => 'pending']
        );

        $review->setAttribute('kpi_score', $this->scoreService->employeeKpiScore(
            $review->employee_id,
            $review->performance_cycle_id
        ));

        return $this->success($review->load('employee'), 201);
    }

    public function updateReview(Request $request, EmployeeReview $employeeReview): JsonResponse
    {
        $employeeReview->update($request->validate([
            'self_score' => 'nullable|numeric|min:0|max:100',
            'manager_score' => 'nullable|numeric|min:0|max:100',
            'self_comment' => 'nullable|string',
            'manager_comment' => 'nullable|string',
            'status' => 'nullable|string',
        ]));

        if ($employeeReview->self_score !== null && $employeeReview->manager_score === null) {
            $employeeReview->update(['status' => 'self_done']);
        }
        if ($employeeReview->manager_score !== null) {
            $employeeReview->update(['status' => 'manager_done']);
        }

        $employeeReview->setAttribute('kpi_score', $this->scoreService->employeeKpiScore(
            $employeeReview->employee_id,
            $employeeReview->performance_cycle_id
        ));

        return $this->success($employeeReview->fresh('employee'));
    }

    public function finalizeReview(EmployeeReview $employeeReview): JsonResponse
    {
        $review = $this->scoreService->finalize($employeeReview);
        $review->setAttribute('kpi_score', $this->scoreService->employeeKpiScore(
            $review->employee_id,
            $review->performance_cycle_id
        ));

        return $this->success($review);
    }

    private function cycleKpiSummary(PerformanceCycle $cycle): array
    {
        $employeeIds = $cycle->goals->pluck('employee_id')->unique();
        $scores = $employeeIds->map(fn ($id) => $this->scoreService->employeeKpiScore($id, $cycle->id))
            ->filter()
            ->values();

        return [
            'goal_count' => $cycle->goals->count(),
            'employee_count' => $employeeIds->count(),
            'avg_kpi_score' => $scores->isNotEmpty() ? round($scores->avg(), 2) : null,
        ];
    }
}

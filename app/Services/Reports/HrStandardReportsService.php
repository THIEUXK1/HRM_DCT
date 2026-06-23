<?php

namespace App\Services\Reports;

use App\Models\AttendanceSummary;
use App\Models\BenefitEnrollment;
use App\Models\Candidate;
use App\Models\Employee;
use App\Models\EmployeeAwardDiscipline;
use App\Models\EmployeeReview;
use App\Models\EmployeeTermination;
use App\Models\EmployeeTransfer;
use App\Models\EmploymentContract;
use App\Models\Goal;
use App\Models\Interview;
use App\Models\PayrollCycle;
use App\Models\PayrollResult;
use App\Models\PerformanceCycle;
use App\Models\RecruitmentRequest;
use App\Models\TrainingClass;
use App\Models\TrainingEnrollment;
use App\Services\Performance\PerformanceScoreService;
use App\Support\CompanyContext;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Bộ báo cáo HR chuẩn — phục vụ Trung tâm báo cáo (Reports).
 */
class HrStandardReportsService
{
    public function __construct(
        private readonly PerformanceScoreService $scoreService,
        private readonly WorkforceMovementAnalyzer $movementAnalyzer,
    ) {}

    /** @return array{period: string, from: string, to: string, start: Carbon, end: Carbon} */
    public function resolvePeriod(?string $period): array
    {
        $period = $period && preg_match('/^\d{4}-\d{2}$/', $period)
            ? $period
            : now()->format('Y-m');

        $start = Carbon::createFromFormat('Y-m', $period)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        return [
            'period' => $period,
            'from' => $start->toDateString(),
            'to' => $end->toDateString(),
            'start' => $start,
            'end' => $end,
        ];
    }

    public function workforceMovement(?int $companyId, ?int $departmentId, ?string $period): array
    {
        $companyId = $companyId ?? CompanyContext::id();
        $range = $this->resolvePeriod($period);
        $start = $range['start'];
        $end = $range['end'];

        $headcountStart = $this->headcountAt($companyId, $departmentId, $start->copy()->subDay());
        $headcountEnd = $this->headcountAt($companyId, $departmentId, $end);

        $scope = $this->employeeScope($companyId, $departmentId);
        $transition = $this->movementAnalyzer->analyze($scope, $start, $end);

        $newHires = $transition['new_hires'];

        $terminations = $this->employeeScope($companyId, $departmentId)
            ->whereBetween('termination_date', [$start->toDateString(), $end->toDateString()])
            ->count();

        $transfers = EmployeeTransfer::query()
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->when($departmentId, fn ($q) => $q->where(function ($sub) use ($departmentId) {
                $sub->where('from_department_id', $departmentId)
                    ->orWhere('to_department_id', $departmentId);
            }))
            ->whereBetween('effective_date', [$start->toDateString(), $end->toDateString()])
            ->count();

        $avgHeadcount = ($headcountStart + $headcountEnd) / 2;
        $turnoverRate = $avgHeadcount > 0 ? round(($terminations / $avgHeadcount) * 100, 2) : 0;
        $movementRate = $avgHeadcount > 0
            ? round((($newHires + $terminations + $transfers) / $avgHeadcount) * 100, 2)
            : 0;

        $recentHires = $this->employeeScope($companyId, $departmentId)
            ->with(['department:id,name', 'position:id,name'])
            ->whereBetween('hire_date', [$start->toDateString(), $end->toDateString()])
            ->orderByDesc('hire_date')
            ->limit(30)
            ->get(['id', 'employee_code', 'full_name', 'hire_date', 'department_id', 'position_id'])
            ->map(fn ($e) => [
                'id' => $e->id,
                'employee_code' => $e->employee_code,
                'full_name' => $e->full_name,
                'department' => $e->department?->name,
                'position' => $e->position?->name,
                'hire_date' => $e->hire_date?->format('Y-m-d'),
            ])
            ->all();

        $recentTerminations = EmployeeTermination::query()
            ->with(['employee:id,full_name,employee_code,department_id', 'employee.department:id,name'])
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->when($departmentId, fn ($q) => $q->whereHas('employee', fn ($e) => $e->where('department_id', $departmentId)))
            ->whereBetween('termination_date', [$start->toDateString(), $end->toDateString()])
            ->orderByDesc('termination_date')
            ->limit(30)
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'full_name' => $t->employee?->full_name,
                'employee_code' => $t->employee?->employee_code,
                'department' => $t->employee?->department?->name,
                'termination_date' => $t->termination_date?->format('Y-m-d'),
                'reason_type' => $t->reason_type ?? $t->type,
            ])
            ->all();

        return [
            'period' => $range['period'],
            'from' => $range['from'],
            'to' => $range['to'],
            'summary' => [
                'headcount_start' => $headcountStart,
                'headcount_end' => $headcountEnd,
                'headcount_start_breakdown' => $transition['headcount_start_breakdown'],
                'headcount_end_breakdown' => $transition['headcount_end_breakdown'],
                'new_hires' => $newHires,
                'new_hires_note' => $transition['new_hires_note'],
                'probation_ended_in_period' => $transition['probation_ended_in_period'],
                'converted_to_official_in_period' => $transition['converted_to_official_in_period'],
                'failed_probation_in_period' => $transition['failed_probation_in_period'],
                'conversion_rate' => $transition['conversion_rate'],
                'internal_probation_to_official' => $transition['internal_probation_to_official'],
                'net_headcount_change' => $transition['net_headcount_change'],
                'narrative' => $transition['narrative'],
                'terminations' => $terminations,
                'transfers' => $transfers,
                'turnover_rate' => $turnoverRate,
                'movement_rate' => $movementRate,
            ],
            'recent_hires' => $recentHires,
            'recent_terminations' => $recentTerminations,
            'probation_conversions' => $transition['probation_conversions'],
            'failed_probations' => $transition['failed_probations'],
        ];
    }

    public function workforceStructure(?int $companyId, ?int $departmentId): array
    {
        $companyId = $companyId ?? CompanyContext::id();

        $employees = $this->employeeScope($companyId, $departmentId)
            ->with(['department:id,name', 'position:id,name', 'profile'])
            ->whereIn('employment_status', ['active', 'probation'])
            ->get();

        $byDepartment = $this->groupCount($employees, fn ($e) => $e->department?->name ?? 'Chưa phân phòng');
        $byPosition = $this->groupCount($employees, fn ($e) => $e->position?->name ?? 'Chưa có chức danh');
        $byGender = $this->groupCount($employees, fn ($e) => $this->genderLabel($e->gender));
        $byAge = $this->groupCount($employees, fn ($e) => $this->ageBand($e->date_of_birth));
        $byEducation = $this->groupCount($employees, fn ($e) => $e->profile?->education_level ?: 'Chưa khai báo');
        $byTenure = $this->groupCount($employees, fn ($e) => $this->tenureBand($e->hire_date));
        $byContract = $this->contractTypeDistribution($companyId, $departmentId);

        return [
            'summary' => ['total' => $employees->count()],
            'by_department' => $byDepartment,
            'by_position' => $byPosition,
            'by_gender' => $byGender,
            'by_age' => $byAge,
            'by_education' => $byEducation,
            'by_tenure' => $byTenure,
            'by_contract_type' => $byContract,
        ];
    }

    public function recruitment(?int $companyId, ?string $period): array
    {
        $companyId = $companyId ?? CompanyContext::id();
        $range = $this->resolvePeriod($period);
        $start = $range['start'];
        $end = $range['end'];

        $openHeadcount = (int) RecruitmentRequest::query()
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->whereIn('status', ['approved', 'open'])
            ->sum('headcount');

        $candidatesApplied = Candidate::query()
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->whereBetween('created_at', [$start, $end->copy()->endOfDay()])
            ->count();

        $interviews = Interview::query()
            ->whereHas('candidate', fn ($q) => $q->when($companyId, fn ($c) => $c->where('company_id', $companyId)))
            ->whereBetween('scheduled_at', [$start, $end->copy()->endOfDay()])
            ->count();

        $hired = Candidate::query()
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->where('stage', 'hired')
            ->whereBetween('updated_at', [$start, $end->copy()->endOfDay()])
            ->count();

        $successRate = $candidatesApplied > 0 ? round(($hired / $candidatesApplied) * 100, 1) : 0;

        $avgDays = Candidate::query()
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->where('stage', 'hired')
            ->whereBetween('updated_at', [$start, $end->copy()->endOfDay()])
            ->get(['created_at', 'updated_at'])
            ->map(fn ($c) => $c->created_at?->diffInDays($c->updated_at))
            ->filter(fn ($d) => $d !== null)
            ->avg();

        $byStage = Candidate::query()
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->selectRaw('stage, count(*) as total')
            ->groupBy('stage')
            ->pluck('total', 'stage')
            ->all();

        return [
            'period' => $range['period'],
            'summary' => [
                'open_positions' => $openHeadcount,
                'candidates_applied' => $candidatesApplied,
                'interviews' => $interviews,
                'hired' => $hired,
                'success_rate' => $successRate,
                'avg_days_to_hire' => $avgDays ? round($avgDays, 1) : null,
                'recruitment_cost' => null,
                'note_cost' => 'Chi phí tuyển dụng: chưa có module chi phí — cần tích hợp sau.',
            ],
            'by_stage' => $byStage,
        ];
    }

    public function turnover(?int $companyId, ?int $departmentId, ?string $period): array
    {
        $companyId = $companyId ?? CompanyContext::id();
        $range = $this->resolvePeriod($period);
        $start = $range['start'];
        $end = $range['end'];

        $terminations = EmployeeTermination::query()
            ->with(['employee:id,full_name,employee_code,department_id', 'employee.department:id,name'])
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->when($departmentId, fn ($q) => $q->whereHas('employee', fn ($e) => $e->where('department_id', $departmentId)))
            ->whereBetween('termination_date', [$start->toDateString(), $end->toDateString()])
            ->get();

        $total = $terminations->count();
        $voluntaryTypes = ['resignation', 'retirement', 'contract_end'];
        $voluntary = $terminations->filter(fn ($t) => in_array($t->reason_type ?? $t->type, $voluntaryTypes, true))->count();
        $involuntary = $total - $voluntary;

        $byReason = $terminations->groupBy(fn ($t) => $t->reason_type ?? $t->type ?? 'other')
            ->map(fn ($items, $reason) => [
                'reason' => $this->terminationReasonLabel($reason),
                'reason_code' => $reason,
                'count' => $items->count(),
            ])
            ->values()
            ->sortByDesc('count')
            ->values()
            ->all();

        $headcount = $this->headcountAt($companyId, $departmentId, $end);
        $turnoverRate = $headcount > 0 ? round(($total / $headcount) * 100, 2) : 0;

        $deptRates = $terminations->groupBy(fn ($t) => $t->employee?->department_id)
            ->map(function ($items, $deptId) use ($companyId, $end) {
                $name = $items->first()?->employee?->department?->name ?? 'Chưa phân phòng';
                $deptHeadcount = $this->headcountAt($companyId, (int) $deptId ?: null, $end);

                return [
                    'department' => $name,
                    'terminations' => $items->count(),
                    'headcount' => $deptHeadcount,
                    'rate' => $deptHeadcount > 0 ? round(($items->count() / $deptHeadcount) * 100, 2) : 0,
                ];
            })
            ->values()
            ->sortByDesc('rate')
            ->values()
            ->all();

        $recommendations = $this->turnoverRecommendations($turnoverRate, $byReason, $deptRates);

        return [
            'period' => $range['period'],
            'summary' => [
                'total_terminations' => $total,
                'voluntary' => $voluntary,
                'involuntary' => $involuntary,
                'voluntary_rate' => $total > 0 ? round(($voluntary / $total) * 100, 1) : 0,
                'turnover_rate' => $turnoverRate,
            ],
            'by_reason' => $byReason,
            'by_department' => $deptRates,
            'recommendations' => $recommendations,
        ];
    }

    public function attendanceLeave(?int $companyId, ?int $departmentId, ?string $period): array
    {
        $companyId = $companyId ?? CompanyContext::id();
        $range = $this->resolvePeriod($period);

        $query = AttendanceSummary::with(['employee:id,full_name,employee_code,department_id', 'employee.department:id,name'])
            ->where('company_id', $companyId)
            ->where('period', $range['period'])
            ->when($departmentId, fn ($q) => $q->whereHas('employee', fn ($e) => $e->where('department_id', $departmentId)));

        $rows = $query->get();

        $summary = [
            'employee_count' => $rows->count(),
            'total_work_days' => round($rows->sum('work_days'), 2),
            'avg_work_days' => $rows->count() ? round($rows->avg('work_days'), 2) : 0,
            'total_ot_hours' => round($rows->sum('ot_hours'), 2),
            'total_leave_days' => round($rows->sum('leave_days'), 2),
            'paid_leave_days' => round($rows->sum('paid_leave_days'), 2),
            'unpaid_leave_days' => round($rows->sum('unpaid_leave_days'), 2),
            'total_absent_days' => round($rows->sum('absent_days'), 2),
            'total_late_minutes' => round($rows->sum('late_minutes'), 2),
            'late_incidents' => (int) $rows->sum('late_count'),
            'early_leaves' => (int) $rows->sum('early_count'),
        ];

        $details = $rows->map(fn ($s) => [
            'employee_id' => $s->employee_id,
            'full_name' => $s->employee?->full_name,
            'employee_code' => $s->employee?->employee_code,
            'department' => $s->employee?->department?->name,
            'work_days' => (float) $s->work_days,
            'leave_days' => (float) $s->leave_days,
            'unpaid_leave_days' => (float) $s->unpaid_leave_days,
            'ot_hours' => (float) $s->ot_hours,
            'late_minutes' => (float) $s->late_minutes,
            'absent_days' => (float) $s->absent_days,
            'is_locked' => (bool) $s->is_locked,
        ])->sortByDesc('absent_days')->values()->all();

        return [
            'period' => $range['period'],
            'summary' => $summary,
            'rows' => $details,
        ];
    }

    public function payrollBenefits(?int $companyId, ?int $departmentId, ?string $period): array
    {
        $companyId = $companyId ?? CompanyContext::id();
        $range = $this->resolvePeriod($period);

        $cycle = PayrollCycle::query()
            ->where('company_id', $companyId)
            ->where('period', $range['period'])
            ->orderByDesc('id')
            ->first();

        $results = collect();
        if ($cycle) {
            $results = PayrollResult::with(['employee:id,full_name,employee_code,department_id', 'employee.department:id,name'])
                ->where('payroll_cycle_id', $cycle->id)
                ->when($departmentId, fn ($q) => $q->whereHas('employee', fn ($e) => $e->where('department_id', $departmentId)))
                ->get();
        }

        $benefitCost = BenefitEnrollment::query()
            ->active()
            ->whereHas('employee', function ($q) use ($companyId, $departmentId) {
                $q->when($companyId, fn ($c) => $c->where('company_id', $companyId))
                    ->when($departmentId, fn ($c) => $c->where('department_id', $departmentId));
            })
            ->with('plan:id,name,category,value,value_type')
            ->get()
            ->sum(fn ($e) => is_numeric($e->effectiveValue()) ? (float) $e->effectiveValue() : 0);

        $byDepartment = $results->groupBy(fn ($r) => $r->employee?->department?->name ?? 'Chưa phân phòng')
            ->map(fn ($items, $dept) => [
                'department' => $dept,
                'headcount' => $items->count(),
                'gross' => round($items->sum('gross_salary'), 0),
                'net' => round($items->sum('net_salary'), 0),
                'bhxh_employer' => round($items->sum('bhxh_employer'), 0),
            ])
            ->values()
            ->sortByDesc('gross')
            ->values()
            ->all();

        $headcount = max(1, $results->count());

        return [
            'period' => $range['period'],
            'cycle_status' => $cycle?->status,
            'summary' => [
                'headcount' => $results->count(),
                'total_gross' => round($results->sum('gross_salary'), 0),
                'total_net' => round($results->sum('net_salary'), 0),
                'total_pit' => round($results->sum('pit_amount'), 0),
                'total_bhxh_employee' => round($results->sum('bhxh_employee'), 0),
                'total_bhxh_employer' => round($results->sum('bhxh_employer'), 0),
                'benefit_cost_estimate' => round($benefitCost, 0),
                'avg_cost_per_employee' => round(($results->sum('gross_salary') + $benefitCost) / $headcount, 0),
            ],
            'by_department' => $byDepartment,
            'rows' => $results->map(fn ($r) => [
                'full_name' => $r->employee?->full_name,
                'department' => $r->employee?->department?->name,
                'gross_salary' => (float) $r->gross_salary,
                'net_salary' => (float) $r->net_salary,
                'pit_amount' => (float) $r->pit_amount,
                'bhxh_employee' => (float) $r->bhxh_employee,
                'bhxh_employer' => (float) $r->bhxh_employer,
            ])->values()->all(),
        ];
    }

    public function training(?int $companyId, ?string $period): array
    {
        $companyId = $companyId ?? CompanyContext::id();
        $tenantId = CompanyContext::tenantId();
        $range = $this->resolvePeriod($period);
        $start = $range['start'];
        $end = $range['end'];

        $classes = TrainingClass::query()
            ->whereHas('course', fn ($q) => $q->when($tenantId, fn ($c) => $c->where('tenant_id', $tenantId)))
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('start_date', [$start, $end])
                    ->orWhereBetween('end_date', [$start, $end]);
            })
            ->with('course:id,name,code')
            ->get();

        $enrollments = TrainingEnrollment::query()
            ->whereHas('employee', fn ($q) => $q->when($companyId, fn ($e) => $e->where('company_id', $companyId)))
            ->whereHas('trainingClass', function ($q) use ($start, $end) {
                $q->where(function ($d) use ($start, $end) {
                    $d->whereBetween('start_date', [$start, $end])
                        ->orWhereBetween('end_date', [$start, $end]);
                });
            })
            ->with(['employee:id,full_name', 'trainingClass.course:id,name'])
            ->get();

        $completed = $enrollments->where('status', 'completed');
        $avgScore = $completed->avg('score');

        $byCourse = $enrollments->groupBy(fn ($e) => $e->trainingClass?->course?->name ?? 'Khác')
            ->map(fn ($items, $name) => [
                'course' => $name,
                'participants' => $items->count(),
                'completed' => $items->where('status', 'completed')->count(),
                'avg_score' => round($items->where('status', 'completed')->avg('score') ?? 0, 1),
            ])
            ->values()
            ->sortByDesc('participants')
            ->values()
            ->all();

        return [
            'period' => $range['period'],
            'summary' => [
                'class_count' => $classes->count(),
                'participants' => $enrollments->unique('employee_id')->count(),
                'enrollment_count' => $enrollments->count(),
                'completed_count' => $completed->count(),
                'avg_score' => $avgScore ? round($avgScore, 1) : null,
                'training_cost' => null,
                'note_cost' => 'Chi phí đào tạo: chưa có trường chi phí trên lớp học.',
            ],
            'by_course' => $byCourse,
            'next_needs' => $this->trainingNeedsHint($companyId),
        ];
    }

    public function performanceExtended(?int $cycleId, ?int $departmentId, ?int $companyId): array
    {
        $companyId = $companyId ?? CompanyContext::id();
        $cycle = $cycleId
            ? PerformanceCycle::findOrFail($cycleId)
            : PerformanceCycle::query()->orderByDesc('period')->first();

        if (! $cycle) {
            return ['cycle' => null, 'summary' => [], 'employees' => [], 'rating_distribution' => []];
        }

        $employees = $this->employeeScope($companyId, $departmentId)
            ->with(['department', 'position'])
            ->whereIn('employment_status', ['active', 'probation'])
            ->get();

        $reviews = EmployeeReview::query()
            ->where('performance_cycle_id', $cycle->id)
            ->get()
            ->keyBy('employee_id');

        $goals = Goal::query()
            ->where('performance_cycle_id', $cycle->id)
            ->get()
            ->groupBy('employee_id');

        $rows = [];
        $ratings = [];

        foreach ($employees as $employee) {
            $kpi = $this->scoreService->employeeKpiScore($employee->id, $cycle->id);
            $review = $reviews->get($employee->id);
            $rating = $review?->rating;
            if ($rating) {
                $ratings[$rating] = ($ratings[$rating] ?? 0) + 1;
            }

            $rows[] = [
                'employee_id' => $employee->id,
                'full_name' => $employee->full_name,
                'department' => $employee->department?->name,
                'goal_count' => ($goals->get($employee->id) ?? collect())->count(),
                'kpi_score' => $kpi,
                'final_score' => $review?->final_score,
                'rating' => $rating,
                'review_status' => $review?->status,
            ];
        }

        usort($rows, fn ($a, $b) => ($b['final_score'] ?? 0) <=> ($a['final_score'] ?? 0));

        $topPerformers = array_slice(array_filter($rows, fn ($r) => in_array($r['rating'], ['A', 'B'], true)), 0, 5);
        $needImprovement = array_values(array_filter($rows, fn ($r) => in_array($r['rating'], ['D', 'C'], true)));

        return [
            'cycle' => $cycle->only(['id', 'name', 'period', 'status']),
            'summary' => [
                'employee_count' => count($rows),
                'avg_kpi_score' => collect($rows)->pluck('kpi_score')->filter()->avg()
                    ? round(collect($rows)->pluck('kpi_score')->filter()->avg(), 2)
                    : null,
                'avg_final_score' => collect($rows)->pluck('final_score')->filter()->avg()
                    ? round(collect($rows)->pluck('final_score')->filter()->avg(), 2)
                    : null,
                'completed_reviews' => $reviews->where('status', 'completed')->count(),
                'top_performers' => count($topPerformers),
                'need_improvement' => count($needImprovement),
            ],
            'rating_distribution' => collect($ratings)->map(fn ($count, $rating) => [
                'rating' => $rating,
                'count' => $count,
            ])->values()->all(),
            'top_performers' => $topPerformers,
            'need_improvement' => array_slice($needImprovement, 0, 10),
            'employees' => $rows,
        ];
    }

    public function awardsDiscipline(?int $companyId, ?int $departmentId, ?string $period): array
    {
        $companyId = $companyId ?? CompanyContext::id();
        $range = $this->resolvePeriod($period);
        $start = $range['start'];
        $end = $range['end'];

        $records = EmployeeAwardDiscipline::query()
            ->with(['employee:id,full_name,employee_code,department_id', 'employee.department:id,name'])
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->when($departmentId, fn ($q) => $q->whereHas('employee', fn ($e) => $e->where('department_id', $departmentId)))
            ->whereBetween('decision_date', [$start->toDateString(), $end->toDateString()])
            ->orderByDesc('decision_date')
            ->get();

        $awards = $records->where('type', 'award');
        $disciplines = $records->where('type', 'discipline');

        $awardReasons = $awards->groupBy('reason')
            ->map(fn ($items, $reason) => ['reason' => $reason ?: 'Khác', 'count' => $items->count()])
            ->values()
            ->sortByDesc('count')
            ->values()
            ->all();

        $disciplineReasons = $disciplines->groupBy('reason')
            ->map(fn ($items, $reason) => ['reason' => $reason ?: 'Khác', 'count' => $items->count()])
            ->values()
            ->sortByDesc('count')
            ->values()
            ->all();

        return [
            'period' => $range['period'],
            'summary' => [
                'awards_count' => $awards->count(),
                'discipline_count' => $disciplines->count(),
                'award_amount_total' => round($awards->sum('amount'), 0),
            ],
            'award_reasons' => $awardReasons,
            'discipline_reasons' => $disciplineReasons,
            'recent_awards' => $awards->take(15)->map(fn ($r) => [
                'employee' => $r->employee?->full_name,
                'department' => $r->employee?->department?->name,
                'reason' => $r->reason,
                'amount' => (float) $r->amount,
                'decision_date' => $r->decision_date?->format('Y-m-d'),
            ])->values()->all(),
            'recent_disciplines' => $disciplines->take(15)->map(fn ($r) => [
                'employee' => $r->employee?->full_name,
                'department' => $r->employee?->department?->name,
                'reason' => $r->reason,
                'decision_date' => $r->decision_date?->format('Y-m-d'),
            ])->values()->all(),
            'remedial_actions' => [
                'Theo dõi NV bị kỷ luật trong 3–6 tháng',
                'Phối hợp quản lý trực tiếp lập kế hoạch cải thiện',
                'Ghi nhận tiến bộ qua đánh giá KPI kỳ tiếp theo',
            ],
        ];
    }

    public function executiveSummary(?int $companyId, ?int $departmentId, ?string $period): array
    {
        $companyId = $companyId ?? CompanyContext::id();
        $range = $this->resolvePeriod($period);

        $movement = $this->workforceMovement($companyId, $departmentId, $range['period']);
        $structure = $this->workforceStructure($companyId, $departmentId);
        $recruitment = $this->recruitment($companyId, $range['period']);
        $turnover = $this->turnover($companyId, $departmentId, $range['period']);
        $attendance = $this->attendanceLeave($companyId, $departmentId, $range['period']);
        $payroll = $this->payrollBenefits($companyId, $departmentId, $range['period']);
        $training = $this->training($companyId, $range['period']);
        $performance = $this->performanceExtended(null, $departmentId, $companyId);
        $awards = $this->awardsDiscipline($companyId, $departmentId, $range['period']);

        $comments = [];
        if ($movement['summary']['turnover_rate'] > 5) {
            $comments[] = 'Tỷ lệ biến động/nghỉ việc cao — cần rà soát phòng ban và lý do nghỉ.';
        }
        if ($recruitment['summary']['open_positions'] > 0 && $recruitment['summary']['hired'] < $recruitment['summary']['open_positions']) {
            $comments[] = 'Còn vị trí tuyển dụng chưa lấp đầy — đẩy nhanh pipeline ứng viên.';
        }
        if (($attendance['summary']['total_absent_days'] ?? 0) > 0) {
            $comments[] = 'Có ngày vắng không lý do — xem tab Chấm công chi tiết.';
        }
        if (empty($comments)) {
            $comments[] = 'Tình hình nhân sự ổn định trong kỳ — duy trì theo dõi định kỳ.';
        }

        return [
            'period' => $range['period'],
            'headline' => [
                'total_headcount' => $structure['summary']['total'],
                'headcount_start' => $movement['summary']['headcount_start'],
                'headcount_end' => $movement['summary']['headcount_end'],
                'new_hires' => $movement['summary']['new_hires'],
                'terminations' => $movement['summary']['terminations'],
                'turnover_rate' => $turnover['summary']['turnover_rate'],
            ],
            'sections' => [
                'movement' => $movement['summary'],
                'recruitment' => $recruitment['summary'],
                'turnover' => $turnover['summary'],
                'attendance' => $attendance['summary'],
                'payroll' => $payroll['summary'],
                'training' => $training['summary'],
                'performance' => $performance['summary'],
                'awards' => $awards['summary'],
            ],
            'comments' => $comments,
            'recommendations' => array_merge(
                $turnover['recommendations'] ?? [],
                $training['next_needs'] ?? [],
            ),
        ];
    }

    private function employeeScope(?int $companyId, ?int $departmentId): Builder
    {
        return Employee::query()
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->when($departmentId, fn ($q) => $q->where('department_id', $departmentId));
    }

    private function headcountAt(?int $companyId, ?int $departmentId, Carbon $date): int
    {
        return $this->employeeScope($companyId, $departmentId)
            ->whereDate('hire_date', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('termination_date')
                    ->orWhereDate('termination_date', '>', $date);
            })
            ->count();
    }

    /** @param Collection<int, Employee> $employees */
    private function groupCount(Collection $employees, callable $keyFn): array
    {
        $total = max(1, $employees->count());

        return $employees->groupBy($keyFn)
            ->map(fn ($items, $label) => [
                'label' => (string) $label,
                'count' => $items->count(),
                'percent' => round(($items->count() / $total) * 100, 1),
            ])
            ->values()
            ->sortByDesc('count')
            ->values()
            ->all();
    }

    private function genderLabel(?string $gender): string
    {
        return match ($gender) {
            'male', 'M' => 'Nam',
            'female', 'F' => 'Nữ',
            'other' => 'Khác',
            default => 'Chưa khai báo',
        };
    }

    private function ageBand($dateOfBirth): string
    {
        if (! $dateOfBirth) {
            return 'Chưa khai báo';
        }
        $age = Carbon::parse($dateOfBirth)->age;

        return match (true) {
            $age < 25 => 'Dưới 25',
            $age < 35 => '25–34',
            $age < 45 => '35–44',
            $age < 55 => '45–54',
            default => '55+',
        };
    }

    private function tenureBand($hireDate): string
    {
        if (! $hireDate) {
            return 'Chưa khai báo';
        }
        $years = Carbon::parse($hireDate)->diffInYears(now());

        return match (true) {
            $years < 1 => 'Dưới 1 năm',
            $years < 3 => '1–3 năm',
            $years < 5 => '3–5 năm',
            $years < 10 => '5–10 năm',
            default => 'Trên 10 năm',
        };
    }

    private function contractTypeDistribution(?int $companyId, ?int $departmentId): array
    {
        $contracts = EmploymentContract::query()
            ->with('employee:id,department_id,company_id')
            ->where('status', 'active')
            ->when($companyId, fn ($q) => $q->whereHas('employee', fn ($e) => $e->where('company_id', $companyId)))
            ->when($departmentId, fn ($q) => $q->whereHas('employee', fn ($e) => $e->where('department_id', $departmentId)))
            ->get();

        $labels = [
            'indefinite' => 'Không thời hạn',
            'definite' => 'Xác định thời hạn',
            'probation' => 'Thử việc',
            'seasonal' => 'Thời vụ',
            'project' => 'Theo dự án',
        ];

        $total = max(1, $contracts->count());

        return $contracts->groupBy('contract_type')
            ->map(fn ($items, $type) => [
                'label' => $labels[$type] ?? $type,
                'count' => $items->count(),
                'percent' => round(($items->count() / $total) * 100, 1),
            ])
            ->values()
            ->sortByDesc('count')
            ->values()
            ->all();
    }

    private function terminationReasonLabel(string $code): string
    {
        return match ($code) {
            'resignation' => 'Tự nguyện nghỉ',
            'retirement' => 'Về hưu',
            'contract_end' => 'Hết hợp đồng',
            'dismissal', 'termination' => 'Sa thải',
            'redundancy' => 'Cắt giảm nhân sự',
            'death' => 'Qua đời',
            default => $code,
        };
    }

    /** @return list<string> */
    private function turnoverRecommendations(float $rate, array $byReason, array $deptRates): array
    {
        $tips = [];
        if ($rate > 8) {
            $tips[] = 'Tỷ lệ nghỉ việc cao — khảo sát exit interview và điều chỉnh chính sách giữ chân.';
        }
        if (($collect = collect($byReason)->firstWhere('reason_code', 'resignation')) && ($collect['count'] ?? 0) > 0) {
            $tips[] = 'Nhiều trường hợp tự nguyên — xem xét lương thưởng, lộ trình thăng tiến, môi trường làm việc.';
        }
        $hotDept = $deptRates[0] ?? null;
        if ($hotDept && ($hotDept['rate'] ?? 0) > 10) {
            $tips[] = "Phòng {$hotDept['department']} có tỷ lệ nghỉ cao — ưu tiên phỏng vấn quản lý trực tiếp.";
        }
        if (empty($tips)) {
            $tips[] = 'Duy trì chương trình giữ chân và theo dõi hàng quý.';
        }

        return $tips;
    }

    /** @return list<string> */
    private function trainingNeedsHint(?int $companyId): array
    {
        return [
            'Rà soát gap năng lực (tab Năng lực) để lập kế hoạch đào tạo kỳ tới',
            'Ưu tiên đào tạo cho NV mới và NV chuyển vị trí',
        ];
    }
}

<?php

namespace App\Services\Reports;

use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeReview;
use App\Models\EmployeeAwardDiscipline;
use App\Models\Goal;
use App\Models\PayrollCycle;
use App\Models\PerformanceCycle;
use App\Services\Company\CompanyPolicyResolver;
use App\Services\Competency\CompetencyGapService;
use App\Services\Performance\PerformanceScoreService;
use Illuminate\Support\Collection;

class HrAnalyticsReportService
{
    public function __construct(
        private readonly CompetencyGapService $gapService,
        private readonly PerformanceScoreService $scoreService,
    ) {}

    public function competencyGapByTeam(?int $departmentId = null, ?int $companyId = null): array
    {
        $employees = $this->activeEmployees($departmentId, $companyId);
        $rows = [];
        $totalGaps = 0;
        $totalMet = 0;
        $totalItems = 0;

        foreach ($employees as $employee) {
            $matrix = $this->gapService->matrixForEmployee($employee);
            $summary = $matrix['summary'];
            $totalGaps += $summary['gaps'];
            $totalMet += $summary['met'];
            $totalItems += $summary['total'];

            $rows[] = [
                'employee_id' => $employee->id,
                'employee_code' => $employee->employee_code,
                'full_name' => $employee->full_name,
                'department' => $employee->department?->name,
                'position' => $employee->position?->name,
                'coverage_percent' => $summary['coverage_percent'],
                'gaps' => $summary['gaps'],
                'not_assessed' => $summary['not_assessed'],
                'top_gaps' => collect($matrix['items'])
                    ->filter(fn ($i) => in_array($i['gap_status'], ['gap', 'partial'], true))
                    ->take(3)
                    ->map(fn ($i) => [
                        'competency' => $i['competency']?->name,
                        'required_level' => $i['required_level'],
                        'current_level' => $i['current_level'],
                        'gap' => $i['gap'],
                    ])
                    ->values()
                    ->all(),
            ];
        }

        usort($rows, fn ($a, $b) => $b['gaps'] <=> $a['gaps']);

        return [
            'filters' => ['department_id' => $departmentId, 'company_id' => $companyId],
            'summary' => [
                'employee_count' => count($rows),
                'total_gap_items' => $totalGaps,
                'total_met_items' => $totalMet,
                'avg_coverage_percent' => $totalItems > 0
                    ? (int) round(($totalMet / $totalItems) * 100)
                    : 0,
            ],
            'employees' => $rows,
        ];
    }

    public function performanceKpiByTeam(?int $cycleId = null, ?int $departmentId = null, ?int $companyId = null): array
    {
        $cycle = $cycleId
            ? PerformanceCycle::findOrFail($cycleId)
            : PerformanceCycle::query()->orderByDesc('period')->first();

        if (! $cycle) {
            return [
                'cycle' => null,
                'summary' => ['employee_count' => 0, 'avg_kpi_score' => null, 'avg_final_score' => null],
                'employees' => [],
            ];
        }

        $employees = $this->activeEmployees($departmentId, $companyId);
        $goals = Goal::query()
            ->where('performance_cycle_id', $cycle->id)
            ->get()
            ->groupBy('employee_id');

        $reviews = EmployeeReview::query()
            ->where('performance_cycle_id', $cycle->id)
            ->get()
            ->keyBy('employee_id');

        $rows = [];
        $kpiScores = collect();
        $finalScores = collect();

        foreach ($employees as $employee) {
            $kpi = $this->scoreService->employeeKpiScore($employee->id, $cycle->id);
            $review = $reviews->get($employee->id);
            if ($kpi !== null) {
                $kpiScores->push($kpi);
            }
            if ($review?->final_score !== null) {
                $finalScores->push((float) $review->final_score);
            }

            $rows[] = [
                'employee_id' => $employee->id,
                'full_name' => $employee->full_name,
                'department' => $employee->department?->name,
                'position' => $employee->position?->name,
                'goal_count' => ($goals->get($employee->id) ?? collect())->count(),
                'kpi_score' => $kpi,
                'self_score' => $review?->self_score,
                'manager_score' => $review?->manager_score,
                'final_score' => $review?->final_score,
                'rating' => $review?->rating,
                'review_status' => $review?->status,
            ];
        }

        usort($rows, fn ($a, $b) => ($b['kpi_score'] ?? 0) <=> ($a['kpi_score'] ?? 0));

        return [
            'cycle' => $cycle->only(['id', 'name', 'period', 'status']),
            'filters' => [
                'performance_cycle_id' => $cycle->id,
                'department_id' => $departmentId,
                'company_id' => $companyId,
            ],
            'summary' => [
                'employee_count' => count($rows),
                'avg_kpi_score' => $kpiScores->isNotEmpty() ? round($kpiScores->avg(), 2) : null,
                'avg_final_score' => $finalScores->isNotEmpty() ? round($finalScores->avg(), 2) : null,
                'completed_reviews' => $reviews->filter(fn ($r) => $r->status === 'completed')->count(),
            ],
            'employees' => $rows,
        ];
    }

    private function activeEmployees(?int $departmentId, ?int $companyId): Collection
    {
        return Employee::query()
            ->with(['department', 'position'])
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->when($departmentId, fn ($q) => $q->where('department_id', $departmentId))
            ->whereIn('employment_status', ['active', 'probation'])
            ->orderBy('full_name')
            ->get();
    }

    public function hrOverviewReport(?int $departmentId = null, ?int $companyId = null): array
    {
        $companyId = $companyId ?? \App\Support\CompanyContext::id();

        // 1. Thống kê theo trạng thái làm việc (employment_status)
        $statusCounts = Employee::query()
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->when($departmentId, fn ($q) => $q->where('department_id', $departmentId))
            ->select('employment_status', \Illuminate\Support\Facades\DB::raw('count(*) as total'))
            ->groupBy('employment_status')
            ->pluck('total', 'employment_status')
            ->toArray();

        $active = $statusCounts['active'] ?? 0;
        $probation = $statusCounts['probation'] ?? 0;
        $terminated = $statusCounts['terminated'] ?? 0;
        $total = $active + $probation;

        // 2. Thống kê tuyển mới và thôi việc trong tháng hiện tại
        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();

        $newHires = Employee::query()
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->when($departmentId, fn ($q) => $q->where('department_id', $departmentId))
            ->whereBetween('hire_date', [$startOfMonth, $endOfMonth])
            ->count();

        $resignations = Employee::query()
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->when($departmentId, fn ($q) => $q->where('department_id', $departmentId))
            ->whereBetween('termination_date', [$startOfMonth, $endOfMonth])
            ->count();

        // 3. Phân bổ nhân sự theo phòng ban
        $deptQuery = Employee::query()
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->when($departmentId, fn ($q) => $q->where('department_id', $departmentId))
            ->whereIn('employment_status', ['active', 'probation'])
            ->with('department')
            ->get();

        $departmentsDist = $deptQuery->groupBy('department_id')
            ->map(function ($items) {
                $first = $items->first();
                return [
                    'id' => $first->department_id,
                    'name' => $first->department?->name ?? 'Chưa phân phòng',
                    'count' => $items->count(),
                ];
            })->values()->toArray();

        // 4. Tổng số quyết định Khen thưởng và Kỷ luật trong năm nay
        $startOfYear = now()->startOfYear();
        $endOfYear = now()->endOfYear();

        $awardCount = EmployeeAwardDiscipline::query()
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->where('type', 'award')
            ->whereBetween('decision_date', [$startOfYear, $endOfYear])
            ->count();

        $disciplineCount = EmployeeAwardDiscipline::query()
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->where('type', 'discipline')
            ->whereBetween('decision_date', [$startOfYear, $endOfYear])
            ->count();

        return [
            'summary' => [
                'total_active' => $total,
                'active' => $active,
                'probation' => $probation,
                'terminated' => $terminated,
                'new_hires_this_month' => $newHires,
                'terminations_this_month' => $resignations,
                'awards_this_year' => $awardCount,
                'disciplines_this_year' => $disciplineCount,
            ],
            'departments' => $departmentsDist,
        ];
    }

    /** Dashboard quản lý — thống kê gộp, không tải full danh sách NV/ứng viên. */
    public function managerDashboardSummary(?int $companyId = null): array
    {
        $companyId = $companyId ?? \App\Support\CompanyContext::id();
        $now = now();
        $thirtyDaysAgo = $now->copy()->subDays(30)->toDateString();
        $period = $now->format('Y-m');

        $empBase = Employee::query()->when($companyId, fn ($q) => $q->where('company_id', $companyId));

        $statusCounts = (clone $empBase)
            ->select('employment_status', \Illuminate\Support\Facades\DB::raw('count(*) as total'))
            ->groupBy('employment_status')
            ->pluck('total', 'employment_status');

        $inactive = (clone $empBase)->where('is_active', false)->count();
        $total = (clone $empBase)->count();
        $active = (int) ($statusCounts['active'] ?? 0);
        $probation = (int) ($statusCounts['probation'] ?? 0);

        $hired = (clone $empBase)
            ->where('hire_date', '>=', $thirtyDaysAgo)
            ->count();

        $terminated = (clone $empBase)
            ->where('is_active', false)
            ->where('termination_date', '>=', $thirtyDaysAgo)
            ->count();

        $alertCounts = app(\App\Services\Hr\HrComplianceAlertService::class)
            ->dashboardCounts((int) $companyId, $period);

        $candidateBase = \App\Models\Candidate::query()
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId));

        $activeCandidates = (clone $candidateBase)
            ->whereNotIn('stage', ['hired', 'rejected', 'talent_pool'])
            ->count();

        $interviewing = (clone $candidateBase)->where('stage', 'interview')->count();
        $offered = (clone $candidateBase)->where('stage', 'offer')->count();

        $deptRows = (clone $empBase)
            ->whereIn('employment_status', ['active', 'probation'])
            ->select(
                'department_id',
                'employment_status',
                \Illuminate\Support\Facades\DB::raw('count(*) as total')
            )
            ->groupBy('department_id', 'employment_status')
            ->get();

        $deptIds = $deptRows->pluck('department_id')->filter()->unique();
        $deptNames = \App\Models\Department::whereIn('id', $deptIds)->pluck('name', 'id');

        $deptStats = [];
        foreach ($deptRows->groupBy('department_id') as $deptId => $rows) {
            $name = $deptNames[$deptId] ?? 'Chưa phân bộ phận';
            $deptStats[$name] = $deptStats[$name] ?? ['name' => $name, 'total' => 0, 'probation' => 0, 'active' => 0];
            foreach ($rows as $row) {
                $deptStats[$name]['total'] += (int) $row->total;
                if ($row->employment_status === 'probation') {
                    $deptStats[$name]['probation'] += (int) $row->total;
                }
                if ($row->employment_status === 'active') {
                    $deptStats[$name]['active'] += (int) $row->total;
                }
            }
        }

        usort($deptStats, fn ($a, $b) => $b['total'] <=> $a['total']);

        return [
            'kpi' => [
                'total' => $total,
                'active' => $active,
                'probation' => $probation,
                'inactive' => $inactive,
            ],
            'movement' => [
                'hired' => $hired,
                'terminated' => $terminated,
                'transferred' => 0,
                'turnover' => $total > 0 ? round(($terminated / $total) * 100, 1) : 0,
            ],
            'alerts' => $alertCounts,
            'recruitment' => [
                'openPositions' => $activeCandidates > 0 ? (int) ceil($activeCandidates / 2) : 0,
                'activeCandidates' => $activeCandidates,
                'interviewing' => $interviewing,
                'offered' => $offered,
            ],
            'departments' => array_values($deptStats),
        ];
    }

    /**
     * Cross-company group report:
     * Returns per-company headcount + payroll aggregates for the group/tenant.
     */
    public function groupSummary(?int $tenantId = null, ?string $period = null, int $year = 0): array
    {
        $year = $year ?: (int) date('Y');

        $companies = Company::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->orderBy('name')
            ->get();

        $period = $period ?? date('Y-m');

        $rows = [];
        $groupTotals = [
            'total_employees' => 0,
            'active'          => 0,
            'new_hires'       => 0,
            'terminations'    => 0,
            'total_net_salary' => 0,
            'total_gross_salary' => 0,
        ];

        foreach ($companies as $company) {
            $empQuery = Employee::where('company_id', $company->id);

            $total      = (clone $empQuery)->count();
            $active     = (clone $empQuery)->where('employment_status', 'active')->count();
            $newHires   = (clone $empQuery)
                ->where('hire_date', '>=', "{$year}-01-01")
                ->where('hire_date', '<=', "{$year}-12-31")
                ->count();
            $terminations = (clone $empQuery)->where('employment_status', 'terminated')->count();

            // Payroll from most recent calculated cycle for this company
            $cycle = PayrollCycle::where('company_id', $company->id)
                ->when($period, fn ($q) => $q->where('period', $period))
                ->orderByDesc('period')
                ->first();

            $payrollStats = ['net_salary' => 0, 'gross_salary' => 0, 'employee_count' => 0];
            if ($cycle) {
                $payrollStats = $cycle->results()
                    ->selectRaw('COUNT(*) as employee_count, SUM(net_salary) as net_salary, SUM(gross_salary) as gross_salary')
                    ->first()
                    ?->toArray() ?? $payrollStats;
            }

            $policy = CompanyPolicyResolver::for($company->id, $period);

            $rows[] = [
                'company_id'       => $company->id,
                'company_name'     => $company->name,
                'company_code'     => $company->code,
                'industry_code'    => $company->industry_code,
                'policy_template_code' => $company->policy_template_code,
                'standard_working_days' => $policy->getString('standard_working_days'),
                'total_employees'  => $total,
                'active'           => $active,
                'new_hires_ytd'    => $newHires,
                'terminations_ytd' => $terminations,
                'payroll_period'   => $cycle?->period,
                'net_salary'       => (float) $payrollStats['net_salary'],
                'gross_salary'     => (float) $payrollStats['gross_salary'],
                'payroll_headcount' => (int) $payrollStats['employee_count'],
            ];

            $groupTotals['total_employees'] += $total;
            $groupTotals['active']          += $active;
            $groupTotals['new_hires']       += $newHires;
            $groupTotals['terminations']    += $terminations;
            $groupTotals['total_net_salary']   += (float) $payrollStats['net_salary'];
            $groupTotals['total_gross_salary'] += (float) $payrollStats['gross_salary'];
        }

        return [
            'period'       => $period,
            'year'         => $year,
            'group_totals' => $groupTotals,
            'companies'    => $rows,
        ];
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Services\Reports\HrAnalyticsReportService;
use App\Services\Reports\HrStandardReportsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrReportController extends ApiController
{
    public function __construct(
        private readonly HrAnalyticsReportService $reports,
        private readonly HrStandardReportsService $standardReports,
    ) {}

    private function reportFilters(Request $request): array
    {
        return $request->validate([
            'department_id' => 'nullable|exists:departments,id',
            'company_id' => 'nullable|exists:companies,id',
            'period' => 'nullable|string|regex:/^\d{4}-\d{2}$/',
            'performance_cycle_id' => 'nullable|exists:performance_cycles,id',
        ]);
    }

    public function competencyGaps(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'department_id' => 'nullable|exists:departments,id',
            'company_id' => 'nullable|exists:companies,id',
        ]);

        return $this->success($this->reports->competencyGapByTeam(
            $filters['department_id'] ?? null,
            $filters['company_id'] ?? null,
        ));
    }

    public function performanceKpi(Request $request): JsonResponse
    {
        $filters = $this->reportFilters($request);

        return $this->success($this->standardReports->performanceExtended(
            $filters['performance_cycle_id'] ?? null,
            $filters['department_id'] ?? null,
            $filters['company_id'] ?? null,
        ));
    }

    public function managerDashboard(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'company_id' => 'nullable|exists:companies,id',
        ]);

        return $this->success($this->reports->managerDashboardSummary(
            $filters['company_id'] ?? null,
        ));
    }

    public function hrOverview(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'department_id' => 'nullable|exists:departments,id',
            'company_id' => 'nullable|exists:companies,id',
        ]);

        return $this->success($this->reports->hrOverviewReport(
            $filters['department_id'] ?? null,
            $filters['company_id'] ?? null,
        ));
    }

    /** Cross-company group report — requires admin or companies.view */
    public function groupSummary(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'tenant_id' => 'nullable|exists:tenants,id',
            'period'    => 'nullable|string|regex:/^\d{4}-\d{2}$/',
            'year'      => 'nullable|integer|min:2020|max:2030',
        ]);

        return $this->success($this->reports->groupSummary(
            $filters['tenant_id'] ?? null,
            $filters['period'] ?? null,
            $filters['year'] ?? (int) date('Y'),
        ));
    }

    public function workforceMovement(Request $request): JsonResponse
    {
        $filters = $this->reportFilters($request);

        return $this->success($this->standardReports->workforceMovement(
            $filters['company_id'] ?? null,
            $filters['department_id'] ?? null,
            $filters['period'] ?? null,
        ));
    }

    public function workforceStructure(Request $request): JsonResponse
    {
        $filters = $this->reportFilters($request);

        return $this->success($this->standardReports->workforceStructure(
            $filters['company_id'] ?? null,
            $filters['department_id'] ?? null,
        ));
    }

    public function recruitment(Request $request): JsonResponse
    {
        $filters = $this->reportFilters($request);

        return $this->success($this->standardReports->recruitment(
            $filters['company_id'] ?? null,
            $filters['period'] ?? null,
        ));
    }

    public function turnover(Request $request): JsonResponse
    {
        $filters = $this->reportFilters($request);

        return $this->success($this->standardReports->turnover(
            $filters['company_id'] ?? null,
            $filters['department_id'] ?? null,
            $filters['period'] ?? null,
        ));
    }

    public function attendanceLeave(Request $request): JsonResponse
    {
        $filters = $this->reportFilters($request);

        return $this->success($this->standardReports->attendanceLeave(
            $filters['company_id'] ?? null,
            $filters['department_id'] ?? null,
            $filters['period'] ?? null,
        ));
    }

    public function payrollBenefits(Request $request): JsonResponse
    {
        $filters = $this->reportFilters($request);

        return $this->success($this->standardReports->payrollBenefits(
            $filters['company_id'] ?? null,
            $filters['department_id'] ?? null,
            $filters['period'] ?? null,
        ));
    }

    public function training(Request $request): JsonResponse
    {
        $filters = $this->reportFilters($request);

        return $this->success($this->standardReports->training(
            $filters['company_id'] ?? null,
            $filters['period'] ?? null,
        ));
    }

    public function awardsDiscipline(Request $request): JsonResponse
    {
        $filters = $this->reportFilters($request);

        return $this->success($this->standardReports->awardsDiscipline(
            $filters['company_id'] ?? null,
            $filters['department_id'] ?? null,
            $filters['period'] ?? null,
        ));
    }

    public function executiveSummary(Request $request): JsonResponse
    {
        $filters = $this->reportFilters($request);

        return $this->success($this->standardReports->executiveSummary(
            $filters['company_id'] ?? null,
            $filters['department_id'] ?? null,
            $filters['period'] ?? null,
        ));
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Services\Attendance\AttendanceEmployeeDetailService;
use App\Services\Attendance\AttendanceMonthlyGridService;
use App\Services\Attendance\AttendanceTimesheetService;
use App\Services\Export\AttendanceDetailExporter;
use App\Services\Export\CongLuongExporter;
use App\Services\Payroll\CongLuongSheetService;
use App\Support\CompanyContext;
use App\Support\OrgStructureScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceReportController extends ApiController
{
    public function __construct(
        private readonly AttendanceTimesheetService $timesheet,
        private readonly AttendanceEmployeeDetailService $employeeDetail,
        private readonly CongLuongExporter $congLuongExporter,
        private readonly AttendanceDetailExporter $detailExporter,
        private readonly CongLuongSheetService $congLuongSheet,
        private readonly AttendanceMonthlyGridService $monthlyGrid,
    ) {}

    public function dailyTimesheet(Request $request): JsonResponse
    {
        $filters = $this->validatedFilters($request);

        return $this->success($this->timesheet->dailyTimesheet(
            $filters['company_id'],
            $filters['period'],
            $filters['department_id'],
            $filters['branch_id'],
        ));
    }

    public function phasedMonthly(Request $request): JsonResponse
    {
        $filters = $this->validatedFilters($request);

        return $this->success($this->timesheet->phasedMonthlyReport(
            $filters['company_id'],
            $filters['period'],
            $filters['department_id'],
            $filters['branch_id'],
        ));
    }

    public function monthlyGrid(Request $request): JsonResponse
    {
        $filters = $this->validatedFilters($request);

        return $this->success($this->monthlyGrid->report(
            $filters['company_id'],
            $filters['period'],
            $filters['department_id'],
            $filters['branch_id'],
        ));
    }

    public function overtime(Request $request): JsonResponse
    {
        $filters = $this->validatedFilters($request);

        return $this->success($this->timesheet->overtimeReport(
            $filters['company_id'],
            $filters['period'],
            $filters['department_id'],
            $filters['branch_id'],
        ));
    }

    public function diligence(Request $request): JsonResponse
    {
        $filters = $this->validatedFilters($request);

        return $this->success($this->timesheet->diligenceReport(
            $filters['company_id'],
            $filters['period'],
            $filters['department_id'],
            $filters['branch_id'],
        ));
    }

    public function leave(Request $request): JsonResponse
    {
        $filters = $this->validatedFilters($request);

        return $this->success($this->timesheet->leaveReport(
            $filters['company_id'],
            $filters['period'],
            $filters['department_id'],
            $filters['branch_id'],
        ));
    }

    public function terminations(Request $request): JsonResponse
    {
        $filters = $this->validatedFilters($request);

        return $this->success($this->timesheet->terminationReport(
            $filters['company_id'],
            $filters['period'],
            $filters['department_id'],
            $filters['branch_id'],
        ));
    }

    public function employeeDetail(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_id' => 'nullable|exists:companies,id',
            'period' => 'required|regex:/^\d{4}-\d{2}$/',
            'employee_id' => 'required|exists:employees,id',
        ]);

        $companyId = (int) ($data['company_id'] ?? CompanyContext::id());

        return $this->success($this->employeeDetail->detail(
            $companyId,
            (int) $data['employee_id'],
            $data['period'],
        ));
    }

    public function congLuongSheet(Request $request): JsonResponse
    {
        $filters = $this->validatedFilters($request);

        return $this->success($this->congLuongSheet->report(
            $filters['company_id'],
            $filters['period'],
            $filters['department_id'],
            $filters['branch_id'],
        ));
    }

    public function exportCongLuong(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $data = $request->validate([
            'company_id' => 'nullable|exists:companies,id',
            'period' => 'required|regex:/^\d{4}-\d{2}$/',
        ]);

        $companyId = (int) ($data['company_id'] ?? CompanyContext::id());

        return $this->congLuongExporter->download($companyId, $data['period']);
    }

    public function exportEmployeeDetail(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $data = $request->validate([
            'company_id' => 'nullable|exists:companies,id',
            'period' => 'required|regex:/^\d{4}-\d{2}$/',
            'employee_id' => 'required|exists:employees,id',
        ]);

        $companyId = (int) ($data['company_id'] ?? CompanyContext::id());

        return $this->detailExporter->download($companyId, (int) $data['employee_id'], $data['period']);
    }

    /** @return array{company_id: int, period: string, department_id: ?int, branch_id: ?int} */
    private function validatedFilters(Request $request): array
    {
        $data = $request->validate([
            'company_id' => 'nullable|exists:companies,id',
            'period' => 'required|regex:/^\d{4}-\d{2}$/',
            'department_id' => 'nullable|exists:departments,id',
            'branch_id' => 'nullable|exists:branches,id',
        ]);

        $companyId = (int) ($data['company_id'] ?? CompanyContext::id());
        $branchId = isset($data['branch_id']) ? (int) $data['branch_id'] : null;
        $departmentId = isset($data['department_id']) ? (int) $data['department_id'] : null;

        if ($branchId && ! OrgStructureScope::branchBelongsToCompany($branchId, $companyId)) {
            abort(422, 'Chi nhánh không thuộc công ty đang chọn.');
        }

        if ($departmentId && ! OrgStructureScope::departmentBelongsToCompany($departmentId, $companyId)) {
            abort(422, 'Phòng ban không thuộc công ty đang chọn.');
        }

        return [
            'company_id' => $companyId,
            'period' => $data['period'],
            'department_id' => $departmentId,
            'branch_id' => $branchId,
        ];
    }
}

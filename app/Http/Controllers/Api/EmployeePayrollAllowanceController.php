<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\UpsertEmployeePayrollAllowanceRequest;
use App\Services\Payroll\EmployeePayrollAllowanceService;
use App\Support\CompanyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeePayrollAllowanceController extends ApiController
{
    public function __construct(
        private readonly EmployeePayrollAllowanceService $allowanceService,
    ) {}

    public function catalog(): JsonResponse
    {
        return $this->success($this->allowanceService->catalog());
    }

    public function index(Request $request): JsonResponse
    {
        $period = $request->validate([
            'period' => 'required|regex:/^\d{4}-\d{2}$/',
        ])['period'];

        $companyId = (int) ($request->input('company_id') ?? CompanyContext::id());

        return $this->success($this->allowanceService->listForPeriod($companyId, $period));
    }

    public function upsert(UpsertEmployeePayrollAllowanceRequest $request): JsonResponse
    {
        $companyId = CompanyContext::id();
        $sheet = $this->allowanceService->upsert($companyId, $request->validated());

        return $this->success($sheet->load('employee:id,employee_code,full_name'), 201);
    }

    public function copyFromPrevious(Request $request): JsonResponse
    {
        $period = $request->validate([
            'period' => 'required|regex:/^\d{4}-\d{2}$/',
        ])['period'];

        $companyId = (int) ($request->input('company_id') ?? CompanyContext::id());
        $copied = $this->allowanceService->copyFromPreviousPeriod($companyId, $period);

        return $this->success([
            'copied' => $copied,
            'period' => $period,
        ]);
    }
}

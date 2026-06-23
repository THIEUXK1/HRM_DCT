<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StorePayrollFormulaCustomVariableRequest;
use App\Http\Requests\UpdatePayrollFormulaParametersRequest;
use App\Models\PayrollFormulaCustomVariable;
use App\Services\Payroll\PayrollFormulaVariableService;
use App\Support\CompanyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class PayrollFormulaVariableController extends ApiController
{
    public function __construct(
        private readonly PayrollFormulaVariableService $variables,
    ) {}

    public function index(): JsonResponse
    {
        $companyId = CompanyContext::id();

        return $this->success($this->variables->catalog($companyId));
    }

    public function updateParameters(UpdatePayrollFormulaParametersRequest $request): JsonResponse
    {
        try {
            $this->variables->updateParameters(
                CompanyContext::id(),
                $request->parameterValues(),
            );
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success($this->variables->catalog(CompanyContext::id()));
    }

    public function storeCustom(StorePayrollFormulaCustomVariableRequest $request): JsonResponse
    {
        try {
            $row = $this->variables->createCustomVariable(
                CompanyContext::id(),
                $request->validated(),
            );
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success($row, 201);
    }

    public function updateCustom(Request $request, PayrollFormulaCustomVariable $payrollFormulaCustomVariable): JsonResponse
    {
        $this->assertCompanyOwns($payrollFormulaCustomVariable);

        $data = $request->validate([
            'code' => ['sometimes', 'string', 'max:64', 'regex:/^[a-z][a-z0-9_]{1,47}$/'],
            'label' => ['sometimes', 'string', 'max:255'],
            'value' => ['sometimes', 'numeric'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        try {
            $row = $this->variables->updateCustomVariable($payrollFormulaCustomVariable, $data);
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success($row);
    }

    public function destroyCustom(PayrollFormulaCustomVariable $payrollFormulaCustomVariable): JsonResponse
    {
        $this->assertCompanyOwns($payrollFormulaCustomVariable);
        $payrollFormulaCustomVariable->delete();

        return $this->success(null);
    }

    private function assertCompanyOwns(PayrollFormulaCustomVariable $variable): void
    {
        if ($variable->company_id !== CompanyContext::id()) {
            abort(404);
        }
    }
}

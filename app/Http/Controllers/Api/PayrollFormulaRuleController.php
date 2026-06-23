<?php

namespace App\Http\Controllers\Api;

use App\Models\PayrollFormulaRule;
use App\Services\Payroll\PayrollFormulaService;
use App\Services\Payroll\PayrollFormulaVariableService;
use App\Support\CompanyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PayrollFormulaRuleController extends ApiController
{
    public function index(): JsonResponse
    {
        $rules = PayrollFormulaRule::orderBy('sort_order')->orderBy('name')->get();

        $companyId = CompanyContext::id();

        return $this->success([
            'rules' => $rules,
            'variables' => PayrollFormulaService::variableHints($companyId),
            'variable_catalog' => app(PayrollFormulaVariableService::class)->catalog($companyId),
            'apply_when_options' => [
                ['value' => PayrollFormulaRule::APPLY_ALL, 'label' => 'Tất cả nhân viên'],
                ['value' => PayrollFormulaRule::APPLY_ACTIVE, 'label' => 'NV đang làm (không thôi việc trong tháng)'],
                ['value' => PayrollFormulaRule::APPLY_TERMINATED, 'label' => 'NV thôi việc trong tháng'],
                ['value' => PayrollFormulaRule::APPLY_PERFORMANCE, 'label' => 'Có điểm KPI đã chốt'],
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $companyId = \App\Support\CompanyContext::id();

        $data = $request->validate([
            'code' => [
                'required', 'string', 'max:64',
                Rule::unique('payroll_formula_rules')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'name' => 'required|string|max:255',
            'target_field' => 'required|string|max:64',
            'apply_when' => 'required|in:all,active,terminated_in_month,has_performance_score',
            'formula' => 'required|string|max:2000',
            'category' => 'required|in:earning,deduction',
            'is_taxable' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer|min:0',
            'description' => 'nullable|string|max:500',
        ]);

        $rule = PayrollFormulaRule::create($data);

        return $this->success($rule, 201);
    }

    public function update(Request $request, PayrollFormulaRule $payrollFormulaRule): JsonResponse
    {
        $companyId = \App\Support\CompanyContext::id();

        $data = $request->validate([
            'code' => [
                'required', 'string', 'max:64',
                Rule::unique('payroll_formula_rules')
                    ->where(fn ($q) => $q->where('company_id', $companyId))
                    ->ignore($payrollFormulaRule->id),
            ],
            'name' => 'required|string|max:255',
            'target_field' => 'required|string|max:64',
            'apply_when' => 'required|in:all,active,terminated_in_month,has_performance_score',
            'formula' => 'required|string|max:2000',
            'category' => 'required|in:earning,deduction',
            'is_taxable' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer|min:0',
            'description' => 'nullable|string|max:500',
        ]);

        $payrollFormulaRule->update($data);

        return $this->success($payrollFormulaRule->fresh());
    }

    public function destroy(PayrollFormulaRule $payrollFormulaRule): JsonResponse
    {
        $payrollFormulaRule->delete();

        return $this->success(null);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $legacyKeys = [
            'performance_bonus_enabled',
            'performance_bonus_rate',
            'termination_unused_leave_days_default',
        ];

        $data = $request->validate([
            'performance_bonus_enabled' => 'sometimes|in:0,1',
            'performance_bonus_rate' => 'sometimes|numeric|min:0|max:1',
            'termination_unused_leave_days_default' => 'sometimes|numeric|min:0|max:365',
        ]);

        $parameters = [];
        foreach ($legacyKeys as $key) {
            if (array_key_exists($key, $data)) {
                $parameters[$key] = $data[$key];
            }
        }

        if ($parameters === []) {
            return $this->error('Không có tham số để cập nhật.', 422);
        }

        app(PayrollFormulaVariableService::class)->updateParameters(CompanyContext::id(), $parameters);

        return $this->success($parameters);
    }
}

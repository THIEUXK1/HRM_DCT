<?php

namespace App\Http\Requests;

use App\Support\CompanyContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePayrollBonusTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = CompanyContext::id();
        $id = $this->route('payrollBonusType')?->id ?? $this->route('payroll_bonus_type')?->id;
        $categories = array_keys(config('leave_payroll_vn.bonus_categories', []));
        $modes = array_keys(config('leave_payroll_vn.bonus_calculation_modes', []));

        return [
            'code' => [
                'required', 'string', 'max:32',
                Rule::unique('payroll_bonus_types')
                    ->where(fn ($q) => $q->where('company_id', $companyId))
                    ->ignore($id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', Rule::in($categories)],
            'breakdown_key' => ['nullable', 'string', 'max:64'],
            'taxable' => ['sometimes', 'boolean'],
            'counts_in_gross' => ['sometimes', 'boolean'],
            'calculation_mode' => ['sometimes', 'string', Rule::in($modes)],
            'default_rate' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'default_amount' => ['nullable', 'integer', 'min:0'],
            'legal_reference' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}

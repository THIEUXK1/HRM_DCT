<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertEmployeePayrollAllowanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = (int) ($this->input('company_id') ?? \App\Support\CompanyContext::id());
        $catalogKeys = array_keys(config('payroll_allowances.catalog', []));

        return [
            'employee_id' => [
                'required',
                'integer',
                Rule::exists('employees', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'period' => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'allowances' => ['nullable', 'array'],
            'allowances.*' => ['nullable', 'numeric', 'min:0'],
            'travel_support_amount' => ['nullable', 'numeric', 'min:0'],
            'travel_eligible' => ['sometimes', 'boolean'],
            'prev_month_adjustment' => ['nullable', 'numeric'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('allowances') && is_array($this->allowances)) {
            $catalogKeys = array_keys(config('payroll_allowances.catalog', []));
            $filtered = [];
            foreach ($catalogKeys as $key) {
                if (array_key_exists($key, $this->allowances)) {
                    $filtered[$key] = $this->allowances[$key];
                }
            }
            $this->merge(['allowances' => $filtered]);
        }
    }
}

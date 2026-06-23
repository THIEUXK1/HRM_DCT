<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmploymentContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $contractId = $this->route('employment_contract')?->id ?? null;

        return [
            'employee_id' => ['required', 'exists:employees,id'],
            'contract_number' => ['required', 'string', 'max:100', 'unique:employment_contracts,contract_number'.($contractId ? ','.$contractId : '')],
            'contract_type' => ['required', Rule::in(array_keys(config('hr_vn.contract_types')))],
            'job_title_on_contract' => ['nullable', 'string', 'max:255'],
            'work_location' => ['nullable', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'signed_date' => ['nullable', 'date'],
            'probation_months' => ['nullable', 'integer', 'min:0', 'max:60'],
            'contract_duration_months' => ['nullable', 'integer', 'min:1', 'max:60'],
            'revision_number' => ['nullable', 'integer', 'min:1'],
            'salary_base' => ['required', 'numeric', 'min:0'],
            'probation_salary' => ['nullable', 'integer', 'min:0'],
            'insurance_salary' => ['nullable', 'integer', 'min:0'],
            'allowance_note' => ['nullable', 'string'],
            'salary_currency' => ['nullable', 'string', 'max:10'],
            'working_hours' => ['nullable', Rule::in(array_keys(config('hr_vn.working_hour_types')))],
            'work_schedule' => ['nullable', 'string', 'max:500'],
            'signed_by_employer' => ['nullable', 'string', 'max:255'],
            'signed_by_employee' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(array_keys(config('hr_vn.contract_statuses')))],
            'notes' => ['nullable', 'string'],
            'file_path' => ['nullable', 'string', 'max:500'],
            'file_name' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->contract_type === 'indefinite') {
            $this->merge(['end_date' => null, 'contract_duration_months' => null]);
        }
    }
}

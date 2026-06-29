<?php

namespace App\Http\Requests;

use App\Rules\UniqueNationalIdInTenant;
use App\Rules\UniqueTaxCodeInTenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $employeeId = $this->route('employee')?->id ?? null;
        $suffix = $employeeId ? ','.$employeeId : '';

        return [
            'company_id' => ['required', 'exists:companies,id'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'position_id' => ['nullable', 'exists:positions,id'],
            'leave_entitlement_group_id' => ['nullable', 'exists:leave_entitlement_groups,id'],
            'annual_leave_days_override' => ['nullable', 'numeric', 'min:0', 'max:60'],
            'employee_code' => ['required', 'string', 'max:100', 'unique:employees,employee_code'.$suffix],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'full_name' => ['required', 'string', 'max:510'],
            'email' => ['nullable', 'email', 'max:255', 'unique:employees,email'.$suffix],
            'phone' => ['nullable', 'string', 'max:50'],
            'personal_email' => ['nullable', 'email', 'max:255'],
            'gender' => ['nullable', Rule::in(array_keys(config('hr_vn.genders')))],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'place_of_birth' => ['nullable', 'string', 'max:255'],
            'origin_place' => ['nullable', 'string', 'max:255'],
            'hire_date' => ['nullable', 'date'],
            'probation_end_date' => ['nullable', 'date'],
            'official_start_date' => ['nullable', 'date'],
            'employment_status' => ['nullable', Rule::in(array_keys(config('hr_vn.employment_statuses')))],
            'employment_type' => ['nullable', Rule::in(array_keys(config('hr_vn.employment_types')))],
            'work_location' => ['nullable', 'string', 'max:255'],
            'termination_date' => ['nullable', 'date'],
            'termination_reason' => ['nullable', 'string', 'max:500'],
            'manager_id' => ['nullable', 'exists:employees,id'],
            'work_email' => ['nullable', 'email', 'max:255'],
            'work_phone' => ['nullable', 'string', 'max:50'],
            'national_id' => [
                'nullable', 'string', 'max:20', 'regex:/^[0-9]{9,12}$/',
                new UniqueNationalIdInTenant($employeeId, $this->input('company_id')),
            ],
            'id_card_type' => ['nullable', Rule::in(array_keys(config('hr_vn.id_card_types')))],
            'id_card_issue_date' => ['nullable', 'date'],
            'id_card_issue_place' => ['nullable', 'string', 'max:255'],
            'id_card_expiry_date' => ['nullable', 'date', 'after:id_card_issue_date'],
            'tax_code' => [
                'nullable', 'string', 'max:20', 'regex:/^[0-9]{10}(-[0-9]{3})?$/',
                new UniqueTaxCodeInTenant($employeeId, $this->input('company_id')),
            ],
            'social_insurance_number' => ['nullable', 'string', 'max:20'],
            'health_insurance_card' => ['nullable', 'string', 'max:50'],
            'bhxh_start_date' => ['nullable', 'date'],
            'insurance_salary' => ['nullable', 'integer', 'min:0'],
            'pit_dependents_count' => ['nullable', 'integer', 'min:0', 'max:20'],
            'union_member' => ['sometimes', 'boolean'],
            'bank_account' => ['nullable', 'string', 'max:30'],
            'bank_account_name' => ['nullable', 'string', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_branch' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'permanent_address' => ['nullable', 'string', 'max:500'],
            'temporary_address' => ['nullable', 'string', 'max:500'],
            'ward' => ['nullable', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'nationality' => ['nullable', 'string', 'max:10'],
            'ethnicity' => ['nullable', 'string', 'max:100'],
            'religion' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'national_id.regex' => 'Số CCCD/CMND phải gồm 9–12 chữ số.',
            'tax_code.regex' => 'Mã số thuế cá nhân không hợp lệ (10 số hoặc 10-3).',
        ];
    }
}

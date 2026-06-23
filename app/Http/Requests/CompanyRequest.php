<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = $this->route('company')?->id ?? null;
        $templateCodes = array_keys(config('company_policy_templates', []));

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'unique:companies,code' . ($companyId ? ',' . $companyId : '')],
            'employee_code_prefix' => ['nullable', 'string', 'max:10', 'unique:companies,employee_code_prefix' . ($companyId ? ',' . $companyId : '')],
            'industry_code' => ['nullable', 'string', 'max:32', Rule::in($templateCodes)],
            'policy_template_code' => ['nullable', 'string', 'max:32', Rule::in($templateCodes)],
            'tax_code' => ['nullable', 'string', 'max:100'],
            'social_insurance_unit_code' => ['nullable', 'string', 'max:50'],
            'social_insurance_agency' => ['nullable', 'string', 'max:255'],
            'legal_representative' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}

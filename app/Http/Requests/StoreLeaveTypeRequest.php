<?php

namespace App\Http\Requests;

use App\Support\CompanyContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeaveTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = CompanyContext::id();

        return [
            'code' => [
                'required', 'string', 'max:32',
                Rule::unique('leave_types')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'name' => ['required', 'string', 'max:255'],
            'cell_symbol' => ['nullable', 'string', 'max:8'],
            'is_paid' => ['sometimes', 'boolean'],
            'payroll_category' => ['sometimes', 'string', Rule::in([
                'company_paid', 'company_unpaid', 'bhxh_benefit',
            ])],
            'affects_diligence' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string', 'max:1000'],
            'day_count_mode' => ['sometimes', 'string', Rule::in(['workday', 'calendar'])],
            'requires_approval' => ['sometimes', 'boolean'],
            'legal_reference' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}

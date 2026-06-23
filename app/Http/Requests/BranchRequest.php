<?php

namespace App\Http\Requests;

use App\Support\CompanyContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class BranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $companyId = CompanyContext::id();
        if ($companyId && ! $this->user()?->hasRole('admin')) {
            $this->merge(['company_id' => $companyId]);
        }
    }

    public function rules(): array
    {
        return [
            'company_id' => ['required', 'exists:companies,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:500'],
            'manager_id' => ['nullable', 'exists:employees,id'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $contextCompanyId = CompanyContext::id();
            if (! $contextCompanyId || $this->user()?->hasRole('admin')) {
                return;
            }

            if ((int) $this->input('company_id') !== $contextCompanyId) {
                $validator->errors()->add('company_id', 'Chỉ được tạo chi nhánh thuộc công ty đang làm việc.');
            }
        });
    }
}

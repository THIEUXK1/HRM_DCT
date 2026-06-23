<?php

namespace App\Http\Requests;

use App\Support\CompanyContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLeaveEntitlementGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = CompanyContext::id();
        $groupId = $this->route('leave_entitlement_group')?->id;

        return [
            'code' => [
                'sometimes', 'string', 'max:40',
                Rule::unique('leave_entitlement_groups', 'code')
                    ->where('company_id', $companyId)
                    ->ignore($groupId),
            ],
            'name' => ['sometimes', 'string', 'max:255'],
            'annual_days' => ['sometimes', 'numeric', 'min:0', 'max:60'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_default' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }
}

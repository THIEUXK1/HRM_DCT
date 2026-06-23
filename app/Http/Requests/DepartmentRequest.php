<?php

namespace App\Http\Requests;

use App\Models\Department;
use App\Services\Hr\CompanyOrgSetupService;
use App\Support\CompanyContext;
use App\Support\OrgStructureScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class DepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('branch_id')) {
            return;
        }

        $companyId = CompanyContext::id();
        if (! $companyId) {
            return;
        }

        $branchId = app(CompanyOrgSetupService::class)->resolveBranchIdForCompany($companyId);
        $this->merge(['branch_id' => $branchId]);
    }

    public function rules(): array
    {
        $parentRules = ['nullable', 'exists:departments,id'];
        $department = $this->route('department');
        if ($department) {
            $parentRules[] = 'not_in:'.$department->id;
        }

        return [
            'branch_id' => ['required', 'exists:branches,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50'],
            'manager_id' => ['nullable', 'exists:employees,id'],
            'leave_entitlement_group_id' => ['nullable', 'exists:leave_entitlement_groups,id'],
            'parent_department_id' => $parentRules,
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $branchId = (int) $this->input('branch_id');
            if ($branchId && ! OrgStructureScope::branchBelongsToCompany($branchId)) {
                $validator->errors()->add('branch_id', 'Chi nhánh không thuộc công ty đang làm việc.');
            }

            $parentId = $this->input('parent_department_id');
            if (! $parentId) {
                return;
            }

            $parent = Department::query()->find($parentId);
            if (! $parent) {
                return;
            }

            if ((int) $parent->branch_id !== $branchId) {
                $validator->errors()->add(
                    'parent_department_id',
                    'Phòng ban cha phải cùng chi nhánh với phòng ban/bộ phận này.',
                );
            }

            if ($parent->parent_department_id !== null) {
                $validator->errors()->add(
                    'parent_department_id',
                    'Chỉ được chọn phòng ban cấp cao nhất làm đơn vị cha (bộ phận trực thuộc phòng ban).',
                );
            }

            $department = $this->route('department');
            if ($department && (int) $parentId === $department->id) {
                $validator->errors()->add('parent_department_id', 'Không thể chọn chính mình làm phòng ban cha.');
            }
        });
    }
}

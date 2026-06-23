<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Lọc danh sách nhân viên theo công ty (header), chi nhánh, phòng ban.
 */
class EmployeeQueryScope
{
    public static function apply(Builder $query, Request $request, ?User $user = null): Builder
    {
        $user ??= auth()->user();
        $companyId = CompanyContext::id();

        if ($companyId) {
            $query->where('employees.company_id', $companyId);
        } elseif ($user?->tenant_id) {
            $query->whereHas('company', fn (Builder $q) => $q->where('tenant_id', $user->tenant_id));
        }

        self::applyOrgFilters($query, $request, $companyId);
        self::applyRoleRestriction($query, $user);
        QuerySearch::employee($query, $request->get('search'));

        if ($status = $request->string('employment_status')->toString()) {
            $query->where('employment_status', $status);
        }

        return $query;
    }

    /** Lọc quan hệ employee (leave, OT, contract…). */
    public static function applyOnRelation(Builder $query, string $relation, Request $request): Builder
    {
        $companyId = CompanyContext::id();
        $branchId = $request->filled('branch_id') ? $request->integer('branch_id') : null;
        $departmentId = $request->filled('department_id') ? $request->integer('department_id') : null;

        if ($branchId && $companyId && ! OrgStructureScope::branchBelongsToCompany($branchId, $companyId)) {
            abort(422, 'Chi nhánh không thuộc công ty đang chọn.');
        }

        if ($departmentId && $companyId && ! OrgStructureScope::departmentBelongsToCompany($departmentId, $companyId)) {
            abort(422, 'Phòng ban không thuộc công ty đang chọn.');
        }

        if (! $branchId && ! $departmentId && ! $companyId) {
            return $query;
        }

        return $query->whereHas($relation, function (Builder $q) use ($companyId, $branchId, $departmentId) {
            if ($companyId) {
                $q->where('company_id', $companyId);
            }
            if ($branchId) {
                $q->where('branch_id', $branchId);
            }
            if ($departmentId) {
                $q->where('department_id', $departmentId);
            }
        });
    }

    private static function applyOrgFilters(Builder $query, Request $request, ?int $companyId): void
    {
        if ($branchId = $request->filled('branch_id') ? $request->integer('branch_id') : null) {
            if ($companyId && ! OrgStructureScope::branchBelongsToCompany($branchId, $companyId)) {
                abort(422, 'Chi nhánh không thuộc công ty đang chọn.');
            }
            $query->where('branch_id', $branchId);
        }

        if ($deptId = $request->filled('department_id') ? $request->integer('department_id') : null) {
            if ($companyId && ! OrgStructureScope::departmentBelongsToCompany($deptId, $companyId)) {
                abort(422, 'Phòng ban không thuộc công ty đang chọn.');
            }
            $query->where('department_id', $deptId);
        }
    }

    private static function applyRoleRestriction(Builder $query, ?User $user): void
    {
        if (! $user || $user->hasAnyRole([
            'admin', 'hr_manager', 'auditor',
            'company_admin', 'HR', 'hradmin',
            'payroll_specialist', 'insurance_specialist', 'recruitment_specialist',
        ])) {
            return;
        }

        $employee = $user->employee;
        if ($employee?->department_id) {
            $query->where('department_id', $employee->department_id);
        }
    }
}

<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\Department;
use Illuminate\Database\Eloquent\Builder;

/**
 * Phạm vi cơ cấu tổ chức theo công ty đang chọn (X-Company-Id).
 */
class OrgStructureScope
{
    public static function companyId(): ?int
    {
        return CompanyContext::id();
    }

    public static function applyBranchScope(Builder $query): Builder
    {
        $companyId = self::companyId();
        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        return $query;
    }

    public static function applyDepartmentScope(Builder $query): Builder
    {
        $companyId = self::companyId();
        if ($companyId) {
            $query->whereHas('branch', fn (Builder $q) => $q->where('company_id', $companyId));
        }

        return $query;
    }

    public static function branchBelongsToCompany(int $branchId, ?int $companyId = null): bool
    {
        $companyId ??= self::companyId();
        if (! $companyId) {
            return true;
        }

        return Branch::query()
            ->whereKey($branchId)
            ->where('company_id', $companyId)
            ->exists();
    }

    public static function departmentBelongsToCompany(int $departmentId, ?int $companyId = null): bool
    {
        $companyId ??= self::companyId();
        if (! $companyId) {
            return true;
        }

        return Department::query()
            ->whereKey($departmentId)
            ->whereHas('branch', fn (Builder $q) => $q->where('company_id', $companyId))
            ->exists();
    }
}

<?php

namespace App\Services\Hr;

use App\Models\Branch;
use App\Models\Company;

/**
 * Thiết lập cơ cấu tổ chức tối thiểu cho công ty (chi nhánh mặc định).
 *
 * Phòng ban luôn gắn branch_id trong DB (hỗ trợ đa chi nhánh),
 * nhưng công ty một địa điểm dùng chi nhánh «Trụ sở chính» tự tạo.
 */
class CompanyOrgSetupService
{
    public const DEFAULT_BRANCH_CODE = 'HQ';

    public function ensureDefaultBranch(Company $company): Branch
    {
        $existing = Branch::query()
            ->where('company_id', $company->id)
            ->orderBy('id')
            ->first();

        if ($existing) {
            return $existing;
        }

        return Branch::create([
            'company_id' => $company->id,
            'code' => self::DEFAULT_BRANCH_CODE,
            'name' => 'Trụ sở chính',
            'address' => $company->address,
            'is_active' => true,
        ]);
    }

    public function resolveBranchIdForCompany(int $companyId, ?int $branchId = null): int
    {
        if ($branchId) {
            $branch = Branch::query()
                ->whereKey($branchId)
                ->where('company_id', $companyId)
                ->first();

            if ($branch) {
                return $branch->id;
            }
        }

        $company = Company::query()->findOrFail($companyId);

        return $this->ensureDefaultBranch($company)->id;
    }
}

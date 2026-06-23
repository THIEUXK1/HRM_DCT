<?php

namespace App\Support;

use App\Models\Company;

class CompanyContext
{
    public static function id(): ?int
    {
        $id = app()->bound('current_company_id') ? app('current_company_id') : null;

        return $id ? (int) $id : null;
    }

    public static function set(?int $companyId): void
    {
        if ($companyId) {
            app()->instance('current_company_id', $companyId);
        }
    }

    public static function tenantId(): ?int
    {
        $id = app()->bound('current_tenant_id') ? app('current_tenant_id') : null;

        return $id ? (int) $id : null;
    }

    public static function setTenant(?int $tenantId): void
    {
        if ($tenantId) {
            app()->instance('current_tenant_id', $tenantId);
        }
    }

    public static function setFromCompany(?int $companyId): void
    {
        if (! $companyId) {
            return;
        }

        self::set($companyId);

        $tenantId = Company::query()->whereKey($companyId)->value('tenant_id');
        if ($tenantId) {
            self::setTenant((int) $tenantId);
        }
    }
}

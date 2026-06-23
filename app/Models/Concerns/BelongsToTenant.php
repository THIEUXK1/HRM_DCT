<?php

namespace App\Models\Concerns;

use App\Support\CompanyContext;
use Illuminate\Database\Eloquent\Builder;

trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            $tenantId = CompanyContext::tenantId();
            if ($tenantId && static::tenantScoped()) {
                $builder->where($builder->getModel()->getTable().'.tenant_id', $tenantId);
            }
        });

        static::creating(function ($model) {
            if (static::tenantScoped() && empty($model->tenant_id)) {
                $model->tenant_id = CompanyContext::tenantId();
            }
        });
    }

    protected static function tenantScoped(): bool
    {
        return true;
    }
}

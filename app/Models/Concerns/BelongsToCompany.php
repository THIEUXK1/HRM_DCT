<?php

namespace App\Models\Concerns;

use App\Support\CompanyContext;
use Illuminate\Database\Eloquent\Builder;

trait BelongsToCompany
{
    protected static function bootBelongsToCompany(): void
    {
        static::addGlobalScope('company', function (Builder $builder) {
            $companyId = CompanyContext::id();
            if ($companyId && static::companyScoped()) {
                $builder->where($builder->getModel()->getTable().'.company_id', $companyId);
            }
        });

        static::creating(function ($model) {
            if (static::companyScoped() && empty($model->company_id)) {
                $model->company_id = CompanyContext::id();
            }
        });
    }

    protected static function companyScoped(): bool
    {
        return true;
    }
}

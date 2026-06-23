<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class PayrollFormulaRule extends Model
{
    use BelongsToCompany;

    public const APPLY_ALL = 'all';

    public const APPLY_ACTIVE = 'active';

    public const APPLY_TERMINATED = 'terminated_in_month';

    public const APPLY_PERFORMANCE = 'has_performance_score';

    protected $fillable = [
        'company_id', 'code', 'name', 'target_field', 'apply_when', 'formula',
        'category', 'is_taxable', 'is_active', 'sort_order', 'description',
    ];

    protected function casts(): array
    {
        return [
            'is_taxable' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class PayrollBonusType extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'code', 'name', 'category', 'breakdown_key',
        'taxable', 'counts_in_gross', 'calculation_mode',
        'default_rate', 'default_amount', 'legal_reference', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'taxable' => 'boolean',
            'counts_in_gross' => 'boolean',
            'is_active' => 'boolean',
            'default_rate' => 'decimal:4',
            'default_amount' => 'integer',
        ];
    }
}

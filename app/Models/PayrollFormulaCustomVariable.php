<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class PayrollFormulaCustomVariable extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'code',
        'label',
        'value',
        'description',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }
}

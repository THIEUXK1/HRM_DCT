<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class WorkShift extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'name', 'code', 'start_time', 'end_time', 'break_minutes',
        'is_night_shift', 'crosses_midnight', 'standard_hours', 'legal_reference', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_night_shift' => 'boolean',
            'crosses_midnight' => 'boolean',
            'standard_hours' => 'decimal:2',
        ];
    }
}

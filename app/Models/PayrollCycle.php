<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollCycle extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'period', 'run_number', 'label', 'revision_note',
        'start_date', 'end_date', 'status',
        'locked_at', 'locked_by', 'calculated_at', 'approved_at',
        'unlocked_at', 'unlocked_by',
    ];

    protected $attributes = [
        'run_number' => 1,
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'locked_at' => 'datetime',
            'calculated_at' => 'datetime',
            'approved_at' => 'datetime',
            'unlocked_at' => 'datetime',
        ];
    }

    public function results(): HasMany
    {
        return $this->hasMany(PayrollResult::class);
    }
}

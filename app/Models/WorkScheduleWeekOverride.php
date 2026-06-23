<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkScheduleWeekOverride extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'employee_id', 'week_start',
        'swap_enabled', 'swap_rest_day', 'swap_work_day', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'week_start' => 'date',
            'swap_enabled' => 'boolean',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}

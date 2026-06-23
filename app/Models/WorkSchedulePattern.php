<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkSchedulePattern extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'work_schedule_group_id', 'code', 'name', 'pattern_code',
        'hours_per_day', 'work_days', 'rest_days',
        'allow_weekend_swap', 'allow_continuous', 'max_consecutive_work_days',
        'swap_rest_day', 'swap_work_day', 'work_shift_id', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'work_days' => 'array',
            'rest_days' => 'array',
            'hours_per_day' => 'decimal:2',
            'allow_weekend_swap' => 'boolean',
            'allow_continuous' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(WorkScheduleGroup::class, 'work_schedule_group_id');
    }

    public function workShift(): BelongsTo
    {
        return $this->belongsTo(WorkShift::class);
    }

    public function employeeSchedules(): HasMany
    {
        return $this->hasMany(EmployeeWorkSchedule::class);
    }
}

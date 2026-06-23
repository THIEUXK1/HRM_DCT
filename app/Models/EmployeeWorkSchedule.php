<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeWorkSchedule extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'employee_id', 'work_schedule_group_id', 'work_schedule_pattern_id',
        'effective_from', 'effective_to', 'weekend_swap_enabled', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
            'weekend_swap_enabled' => 'boolean',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(WorkScheduleGroup::class, 'work_schedule_group_id');
    }

    public function pattern(): BelongsTo
    {
        return $this->belongsTo(WorkSchedulePattern::class, 'work_schedule_pattern_id');
    }
}

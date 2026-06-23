<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeReview extends Model
{
    protected $fillable = [
        'performance_cycle_id', 'employee_id', 'self_score', 'manager_score', 'final_score',
        'rating', 'self_comment', 'manager_comment', 'status',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(PerformanceCycle::class, 'performance_cycle_id');
    }
}

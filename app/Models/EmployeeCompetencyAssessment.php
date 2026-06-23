<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeCompetencyAssessment extends Model
{
    protected $fillable = [
        'employee_id', 'competency_id', 'current_level', 'assessed_at', 'assessed_by', 'source', 'course_id',
    ];

    protected function casts(): array
    {
        return ['assessed_at' => 'date'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function competency(): BelongsTo
    {
        return $this->belongsTo(Competency::class);
    }
}

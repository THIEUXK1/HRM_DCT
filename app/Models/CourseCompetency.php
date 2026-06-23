<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseCompetency extends Model
{
    protected $fillable = [
        'course_id',
        'competency_id',
        'granted_level',
        'min_score',
    ];

    protected function casts(): array
    {
        return ['min_score' => 'decimal:2'];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function competency(): BelongsTo
    {
        return $this->belongsTo(Competency::class);
    }
}

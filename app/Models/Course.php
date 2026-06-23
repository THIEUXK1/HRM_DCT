<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    use \App\Models\Concerns\BelongsToTenant;
    protected $fillable = [
        'tenant_id', 'course_category_id', 'code', 'name', 'type', 'duration_hours', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(CourseCategory::class, 'course_category_id');
    }

    public function classes(): HasMany
    {
        return $this->hasMany(TrainingClass::class);
    }

    public function courseCompetencies(): HasMany
    {
        return $this->hasMany(CourseCompetency::class);
    }
}

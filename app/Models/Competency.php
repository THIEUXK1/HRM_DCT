<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Competency extends Model
{
    protected $fillable = ['competency_group_id', 'name', 'code', 'max_level'];

    public function group(): BelongsTo
    {
        return $this->belongsTo(CompetencyGroup::class, 'competency_group_id');
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(EmployeeCompetencyAssessment::class);
    }
}

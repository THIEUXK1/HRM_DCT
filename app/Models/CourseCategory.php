<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourseCategory extends Model
{
    use \App\Models\Concerns\BelongsToTenant;
    protected $fillable = ['tenant_id', 'name', 'code'];

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }
}

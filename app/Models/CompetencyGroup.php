<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompetencyGroup extends Model
{
    use \App\Models\Concerns\BelongsToTenant;
    protected $fillable = ['tenant_id', 'name'];

    public function competencies(): HasMany
    {
        return $this->hasMany(Competency::class);
    }
}

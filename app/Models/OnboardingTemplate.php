<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OnboardingTemplate extends Model
{
    use \App\Models\Concerns\BelongsToTenant;
    protected $fillable = ['tenant_id', 'name', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(OnboardingTask::class);
    }
}

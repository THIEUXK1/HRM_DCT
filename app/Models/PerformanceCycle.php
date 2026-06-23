<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PerformanceCycle extends Model
{
    use \App\Models\Concerns\BelongsToTenant;
    protected $fillable = [
        'tenant_id', 'name', 'period', 'start_date', 'end_date', 'status',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function goals(): HasMany
    {
        return $this->hasMany(Goal::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(EmployeeReview::class);
    }
}

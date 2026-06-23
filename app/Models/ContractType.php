<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContractType extends Model
{
    use HasFactory, \App\Models\Concerns\BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'is_social_insurance',
        'is_probation',
        'default_duration_months',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_social_insurance' => 'boolean',
            'is_probation' => 'boolean',
            'default_duration_months' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}

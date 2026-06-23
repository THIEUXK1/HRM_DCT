<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayslipTemplate extends Model
{
    protected $fillable = [
        'code',
        'name',
        'blade_view',
        'doc_code',
        'is_bilingual',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_bilingual' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobLevel extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id',
        'code',
        'grade',
        'band',
        'category',
        'name',
        'rank',
        'basic_salary_range_min',
        'basic_salary_range_max',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'rank' => 'integer',
            'basic_salary_range_min' => 'integer',
            'basic_salary_range_max' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}

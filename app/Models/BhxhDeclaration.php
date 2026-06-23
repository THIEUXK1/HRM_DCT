<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BhxhDeclaration extends Model
{
    protected $fillable = [
        'company_id',
        'declaration_type',
        'period',
        'from_date',
        'to_date',
        'format',
        'record_count',
        'error_count',
        'status',
        'file_path',
        'file_name',
        'file_disk',
        'summary',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'from_date' => 'date',
            'to_date' => 'date',
            'record_count' => 'integer',
            'error_count' => 'integer',
            'summary' => 'array',
        ];
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function lines()
    {
        return $this->hasMany(BhxhDeclarationLine::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

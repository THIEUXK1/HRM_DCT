<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BhxhDeclarationLine extends Model
{
    protected $fillable = [
        'bhxh_declaration_id',
        'employee_id',
        'line_no',
        'payload',
        'validation_errors',
        'is_valid',
    ];

    protected function casts(): array
    {
        return [
            'line_no' => 'integer',
            'payload' => 'array',
            'validation_errors' => 'array',
            'is_valid' => 'boolean',
        ];
    }

    public function declaration()
    {
        return $this->belongsTo(BhxhDeclaration::class, 'bhxh_declaration_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}

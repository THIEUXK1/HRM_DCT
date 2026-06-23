<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeDependent extends Model
{
    protected $fillable = [
        'employee_id',
        'full_name',
        'relationship',
        'date_of_birth',
        'id_card_number',
        'tax_dependent_code',
        'effective_from',
        'effective_to',
        'is_active',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}

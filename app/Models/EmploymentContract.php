<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmploymentContract extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'employee_id',
        'contract_number',
        'contract_type',
        'job_title_on_contract',
        'work_location',
        'start_date',
        'end_date',
        'signed_date',
        'probation_months',
        'contract_duration_months',
        'revision_number',
        'salary_base',
        'probation_salary',
        'insurance_salary',
        'allowance_note',
        'salary_currency',
        'working_hours',
        'work_schedule',
        'signed_by_employer',
        'signed_by_employee',
        'status',
        'file_path',
        'file_name',
        'file_disk',
        'mime_type',
        'file_size',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'signed_date' => 'date',
            'probation_months' => 'integer',
            'contract_duration_months' => 'integer',
            'revision_number' => 'integer',
            'probation_salary' => 'integer',
            'insurance_salary' => 'integer',
            'file_size' => 'integer',
            'salary_base' => 'decimal:2',
        ];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}

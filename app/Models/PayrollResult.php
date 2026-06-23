<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PayrollResult extends Model
{
    protected $fillable = [
        'payroll_cycle_id', 'employee_id', 'gross_salary', 'bhxh_employee',
        'bhxh_employer', 'pit_amount', 'other_deductions', 'net_salary', 'breakdown',
    ];

    protected function casts(): array
    {
        return [
            'breakdown' => 'array',
            'gross_salary' => 'decimal:2',
            'bhxh_employee' => 'decimal:2',
            'bhxh_employer' => 'decimal:2',
            'pit_amount' => 'decimal:2',
            'other_deductions' => 'decimal:2',
            'net_salary' => 'decimal:2',
        ];
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(PayrollCycle::class, 'payroll_cycle_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function payslip(): HasOne
    {
        return $this->hasOne(Payslip::class);
    }
}

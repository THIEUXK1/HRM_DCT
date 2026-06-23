<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OvertimeExcessRecord extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'employee_id', 'overtime_request_id', 'period', 'work_date',
        'cap_type', 'legal_hours', 'actual_hours', 'excess_hours',
        'status', 'exclude_from_payroll', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'legal_hours' => 'decimal:2',
            'actual_hours' => 'decimal:2',
            'excess_hours' => 'decimal:2',
            'exclude_from_payroll' => 'boolean',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function overtimeRequest(): BelongsTo
    {
        return $this->belongsTo(OvertimeRequest::class);
    }
}

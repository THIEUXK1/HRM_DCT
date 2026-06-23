<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeePayrollAllowance extends Model
{
    use BelongsToCompany;

        protected $fillable = [
        'company_id',
        'employee_id',
        'period',
        'allowances',
        'travel_support_amount',
        'travel_eligible',
        'prev_month_adjustment',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'allowances' => 'array',
            'travel_support_amount' => 'decimal:2',
            'travel_eligible' => 'boolean',
            'prev_month_adjustment' => 'decimal:2',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}

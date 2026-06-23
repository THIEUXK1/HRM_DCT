<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BenefitEnrollment extends Model
{
    protected $fillable = [
        'employee_id',
        'benefit_plan_id',
        'status',
        'enrolled_at',
        'expires_at',
        'override_value',
        'notes',
        'enrolled_by',
    ];

    protected $casts = [
        'enrolled_at'    => 'date',
        'expires_at'     => 'date',
        'override_value' => 'decimal:2',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function plan()
    {
        return $this->belongsTo(BenefitPlan::class, 'benefit_plan_id');
    }

    public function enrolledBy()
    {
        return $this->belongsTo(User::class, 'enrolled_by');
    }

    /** Effective value: override takes precedence over plan default */
    public function effectiveValue(): float|string
    {
        if ($this->override_value !== null) {
            return (float) $this->override_value;
        }
        return $this->plan?->value_type === 'reimbursement'
            ? 'Hoàn trả thực tế'
            : (float) ($this->plan?->value ?? 0);
    }

    // ── Scopes ────────────────────────────────────────────────────────────
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeForEmployee($query, int $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }
}

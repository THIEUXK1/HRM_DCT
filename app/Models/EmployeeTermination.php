<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeTermination extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'employee_id', 'submitted_by_user_id', 'requested_at',
        'decision_number', 'termination_date', 'notice_period_days',
        'reason', 'handover_note', 'type', 'reason_type', 'signed_by', 'status',
        'handover_tasks_done', 'assets_returned', 'exit_interview_done',
        'accounts_disabled', 'final_settlement_done',
        'notes', 'effective_date', 'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'termination_date' => 'date',
            'effective_date' => 'date',
            'requested_at' => 'datetime',
            'notice_period_days' => 'integer',
            'handover_tasks_done' => 'boolean',
            'assets_returned' => 'boolean',
            'exit_interview_done' => 'boolean',
            'accounts_disabled' => 'boolean',
            'final_settlement_done' => 'boolean',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }
}

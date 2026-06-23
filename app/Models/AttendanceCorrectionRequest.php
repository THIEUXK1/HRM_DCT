<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceCorrectionRequest extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'employee_id', 'correction_reason_id', 'work_date',
        'requested_check_in_at', 'requested_check_out_at', 'note',
        'status', 'approved_by', 'approved_at', 'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'requested_check_in_at' => 'datetime',
            'requested_check_out_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function reason(): BelongsTo
    {
        return $this->belongsTo(AttendanceCorrectionReason::class, 'correction_reason_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}

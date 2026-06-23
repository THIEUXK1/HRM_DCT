<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HrNotification extends Model
{
    protected $table = 'hr_notifications';

    protected $fillable = [
        'user_id',
        'company_id',
        'tenant_id',
        'type',
        'title',
        'body',
        'entity_type',
        'entity_id',
        'action_url',
        'priority',
        'read_at',
        'sent_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    // ── Scopes ────────────────────────────────────────────────────────────

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    // ── Priority constants ────────────────────────────────────────────────

    const PRIORITY_LOW    = 'low';
    const PRIORITY_NORMAL = 'normal';
    const PRIORITY_HIGH   = 'high';
    const PRIORITY_URGENT = 'urgent';

    // ── Type constants ────────────────────────────────────────────────────

    const TYPE_CONTRACT_EXPIRING  = 'contract_expiring';
    const TYPE_CONTRACT_EXPIRED   = 'contract_expired';
    const TYPE_PROBATION_ENDING   = 'probation_ending';
    const TYPE_BIRTHDAY           = 'birthday';
    const TYPE_LEAVE_APPROVED     = 'leave_approved';
    const TYPE_LEAVE_REJECTED     = 'leave_rejected';
    const TYPE_APPROVAL_PENDING   = 'approval_pending';
    const TYPE_PAYROLL_FINALIZED  = 'payroll_finalized';
    const TYPE_ONBOARDING_DUE     = 'onboarding_due';
    const TYPE_TRANSFER_APPROVED  = 'transfer_approved';
    const TYPE_BHXH_DUE           = 'bhxh_due';
    const TYPE_OT_APPROVED        = 'ot_approved';
    const TYPE_OT_CAP_EXCEEDED    = 'ot_cap_exceeded';
    const TYPE_COMPLIANCE_ALERT   = 'compliance_alert';
    const TYPE_CUSTOM             = 'custom';
}

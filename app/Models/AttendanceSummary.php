<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceSummary extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'employee_id', 'period',
        // Công
        'work_days', 'probation_work_days', 'official_work_days', 'standard_work_days',
        'leave_days', 'paid_leave_days', 'unpaid_leave_days', 'bhxh_leave_days',
        'probation_paid_leave_days', 'official_paid_leave_days',
        'probation_unpaid_leave_days', 'official_unpaid_leave_days',
        'probation_bhxh_leave_days', 'official_bhxh_leave_days',
        'forgot_punch_count', 'correction_approved_count',
        'diligence_bonus_eligible', 'diligence_bonus_amount',
        'absent_days',
        // Giờ
        'actual_work_hours', 'standard_work_hours',
        // Công phân loại ngày (Điều 105, 107, 112 BLLĐ 2019)
        'work_weekday_days', 'work_weekend_days', 'work_holiday_days',
        // OT phân loại (Điều 107 BLLĐ 2019)
        'ot_hours', 'ot_weekday_hours', 'ot_weekend_hours', 'ot_holiday_hours',
        // Đêm tổng (Điều 106) — regular work + OT night combined
        'night_hours',
        // Ca đêm thường quy tách theo loại ngày
        'work_night_weekday_hours', 'work_night_weekend_hours', 'work_night_holiday_hours',
        // OT đêm tách theo loại ngày (ot_night_weekday = n1 + n2)
        'ot_night_weekday_hours', 'ot_night_weekday_n2_hours',
        'ot_night_weekend_hours', 'ot_night_holiday_hours',
        // Trễ / sớm
        'late_minutes', 'late_count', 'early_count',
        // Cờ kiểm soát
        'ot_monthly_cap_exceeded', 'is_locked', 'locked_at',
        'attendance_breakdown',
        'compliance_alerts',
    ];

    protected function casts(): array
    {
        return [
            'is_locked'               => 'boolean',
            'ot_monthly_cap_exceeded' => 'boolean',
            'locked_at'               => 'datetime',
            'work_days'               => 'decimal:2',
            'probation_work_days'     => 'decimal:2',
            'official_work_days'      => 'decimal:2',
            'standard_work_days'      => 'decimal:2',
            'leave_days'              => 'decimal:2',
            'paid_leave_days'         => 'decimal:2',
            'unpaid_leave_days'       => 'decimal:2',
            'bhxh_leave_days'         => 'decimal:2',
            'probation_paid_leave_days' => 'decimal:2',
            'official_paid_leave_days'  => 'decimal:2',
            'probation_unpaid_leave_days' => 'decimal:2',
            'official_unpaid_leave_days'  => 'decimal:2',
            'probation_bhxh_leave_days' => 'decimal:2',
            'official_bhxh_leave_days'  => 'decimal:2',
            'forgot_punch_count'        => 'integer',
            'correction_approved_count' => 'integer',
            'diligence_bonus_eligible'  => 'boolean',
            'diligence_bonus_amount'    => 'decimal:0',
            'absent_days'             => 'decimal:2',
            'actual_work_hours'       => 'decimal:2',
            'standard_work_hours'     => 'decimal:2',
            'ot_hours'                => 'decimal:2',
            'ot_weekday_hours'        => 'decimal:2',
            'ot_weekend_hours'        => 'decimal:2',
            'work_weekday_days'           => 'decimal:2',
            'work_weekend_days'           => 'decimal:2',
            'work_holiday_days'           => 'decimal:2',
            'ot_holiday_hours'            => 'decimal:2',
            'night_hours'                 => 'decimal:2',
            'work_night_weekday_hours'    => 'decimal:2',
            'work_night_weekend_hours'    => 'decimal:2',
            'work_night_holiday_hours'    => 'decimal:2',
            'ot_night_weekday_hours'      => 'decimal:2',
            'ot_night_weekday_n2_hours'   => 'decimal:2',
            'ot_night_weekend_hours'      => 'decimal:2',
            'ot_night_holiday_hours'      => 'decimal:2',
            'late_minutes'            => 'decimal:2',
            'attendance_breakdown'    => 'array',
            'compliance_alerts'       => 'array',
        ];
    }

    /** Hệ số OT ngày thường: 150% → cộng thêm 50% so với lương giờ tiêu chuẩn */
    public const OT_RATE_WEEKDAY = 1.5;
    /** Hệ số OT cuối tuần: 200% */
    public const OT_RATE_WEEKEND = 2.0;
    /** Hệ số OT ngày lễ: 300% */
    public const OT_RATE_HOLIDAY = 3.0;
    /** Hệ số làm đêm thêm vào: +30% */
    public const NIGHT_RATE_EXTRA = 0.3;

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}

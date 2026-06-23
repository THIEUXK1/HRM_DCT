<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OvertimeRequest extends Model
{
    use BelongsToCompany;

    /**
     * Loại ngày OT theo Điều 107 BLLĐ 2019:
     *  weekday = ngày thường (150%), weekend = cuối tuần (200%), holiday = ngày lễ (300%)
     */
    public const OT_TYPES = [
        'weekday' => 'Ngày thường (150%)',
        'weekend' => 'Cuối tuần (200%)',
        'holiday' => 'Ngày lễ (300%)',
    ];

    protected $fillable = [
        'company_id', 'employee_id', 'work_date', 'hours',
        'ot_type', 'night_hours',
        'exceeds_daily_cap', 'exceeds_monthly_cap',
        'reason', 'status', 'approved_by', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'work_date'           => 'date',
            'approved_at'         => 'datetime',
            'hours'               => 'decimal:2',
            'night_hours'         => 'decimal:2',
            'exceeds_daily_cap'   => 'boolean',
            'exceeds_monthly_cap' => 'boolean',
        ];
    }

    /** Hệ số lương OT theo loại ngày */
    public function getOtRateAttribute(): float
    {
        return match ($this->ot_type) {
            'weekend' => 2.0,
            'holiday' => 3.0,
            default   => 1.5,
        };
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}

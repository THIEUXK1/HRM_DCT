<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceLog extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'employee_id', 'attendance_device_id', 'work_shift_id', 'work_date',
        'check_in_at', 'check_out_at', 'source', 'external_ref',
        'check_in_latitude', 'check_in_longitude', 'check_out_latitude', 'check_out_longitude',
        'check_in_zone_id', 'check_out_zone_id', 'location_status',
        'work_hours', 'late_minutes', 'early_minutes', 'night_hours',
        'is_weekend', 'is_holiday', 'holiday_name', 'employment_phase',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'check_in_at' => 'datetime',
            'check_out_at' => 'datetime',
            'work_hours' => 'decimal:2',
            'late_minutes' => 'decimal:2',
            'early_minutes' => 'decimal:2',
            'night_hours' => 'decimal:2',
            'is_weekend' => 'boolean',
            'is_holiday' => 'boolean',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(AttendanceDevice::class, 'attendance_device_id');
    }

    public function checkInZone(): BelongsTo
    {
        return $this->belongsTo(AttendanceGeofenceZone::class, 'check_in_zone_id');
    }

    public function checkOutZone(): BelongsTo
    {
        return $this->belongsTo(AttendanceGeofenceZone::class, 'check_out_zone_id');
    }
}

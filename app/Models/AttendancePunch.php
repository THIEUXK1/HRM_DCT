<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendancePunch extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'employee_id', 'attendance_device_id', 'geofence_zone_id',
        'punch_type', 'source', 'punched_at', 'latitude', 'longitude',
        'accuracy_meters', 'is_valid', 'validation_message', 'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'punched_at' => 'datetime',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'is_valid' => 'boolean',
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

    public function zone(): BelongsTo
    {
        return $this->belongsTo(AttendanceGeofenceZone::class, 'geofence_zone_id');
    }
}

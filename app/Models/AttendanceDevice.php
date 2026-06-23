<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AttendanceDevice extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'name', 'code', 'vendor', 'import_format', 'is_active',
        'device_type', 'api_token_hash', 'geofence_zone_id', 'latitude', 'longitude', 'last_punch_at',
        'ip_address', 'port', 'connection_password', 'last_sync_at', 'sync_status', 'sync_message',
        'comm_key', 'serial_number', 'location', 'department_id', 'last_connected_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active'    => 'boolean',
            'latitude'     => 'decimal:7',
            'longitude'    => 'decimal:7',
            'last_punch_at' => 'datetime',
            'last_sync_at' => 'datetime',
            'last_connected_at' => 'datetime',
            'port'         => 'integer',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function hasZKTecoConfig(): bool
    {
        return $this->ip_address !== null && $this->ip_address !== '';
    }

    public function geofenceZone(): BelongsTo
    {
        return $this->belongsTo(AttendanceGeofenceZone::class, 'geofence_zone_id');
    }

    /** @return array{token: string, device: self} */
    public static function issueApiToken(self $device): array
    {
        $plain = Str::random(48);
        $device->update(['api_token_hash' => hash('sha256', $plain)]);

        return ['token' => $plain, 'device' => $device->fresh()];
    }
}

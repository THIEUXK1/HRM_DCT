<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AttendanceGeofenceZone extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'branch_id', 'code', 'name', 'zone_type',
        'latitude', 'longitude', 'radius_meters', 'allowed_sources',
        'gate_token_hash', 'is_active', 'address_note',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'radius_meters' => 'integer',
            'allowed_sources' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function allowsSource(string $source): bool
    {
        $allowed = $this->allowed_sources ?? ['mobile', 'device', 'kiosk', 'qr'];

        return in_array($source, $allowed, true);
    }

    /** @return array{gate_token: string, qr_payload: string} */
    public function issueGateToken(): array
    {
        $plain = Str::random(32);
        $this->update(['gate_token_hash' => hash('sha256', $plain)]);

        return [
            'gate_token' => $plain,
            'qr_payload' => sprintf('EHR-PUNCH|%d|%s|%s', $this->company_id, $this->code, $plain),
        ];
    }

    public static function parseQrPayload(string $payload): ?array
    {
        $parts = explode('|', trim($payload));
        if (count($parts) !== 4 || $parts[0] !== 'EHR-PUNCH') {
            return null;
        }

        return [
            'company_id' => (int) $parts[1],
            'zone_code' => $parts[2],
            'gate_token' => $parts[3],
        ];
    }
}

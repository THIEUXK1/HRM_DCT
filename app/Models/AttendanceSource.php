<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceSource extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'name',
        'type',
        'host',
        'port',
        'database_name',
        'username',
        'password_encrypted',
        'timezone',
        'user_table',
        'checkinout_table',
        'employee_code_field',
        'badge_field',
        'check_time_field',
        'is_active',
        'sync_time',
        'last_tested_at',
        'connection_status',
        'last_error',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'port' => 'integer',
            'is_active' => 'boolean',
            'password_encrypted' => 'encrypted',
            'last_tested_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function syncLogs(): HasMany
    {
        return $this->hasMany(AttendanceSyncLog::class);
    }

    public function rawLogs(): HasMany
    {
        return $this->hasMany(AttendanceRawLog::class);
    }
}

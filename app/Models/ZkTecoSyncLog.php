<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZkTecoSyncLog extends Model
{
    protected $table = 'zkteco_sync_logs';

    protected $fillable = [
        'batch_id', 'employee_id', 'device_id', 'employee_code', 'fingerprint_code',
        'action', 'status', 'message', 'error_detail', 'old_device_data', 'new_device_data',
    ];

    protected function casts(): array
    {
        return [
            'old_device_data' => 'array',
            'new_device_data' => 'array',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ZkTecoSyncBatch::class, 'batch_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(AttendanceDevice::class, 'device_id');
    }
}

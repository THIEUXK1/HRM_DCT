<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ZkTecoSyncBatch extends Model
{
    protected $table = 'zkteco_sync_batches';

    protected $fillable = [
        'sync_type', 'target_device_ids', 'requested_by', 'dry_run', 'status',
        'total_employees', 'total_devices', 'success_count', 'failed_count', 'skipped_count',
        'started_at', 'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'target_device_ids' => 'array',
            'dry_run' => 'boolean',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function requestedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ZkTecoSyncLog::class, 'batch_id');
    }
}

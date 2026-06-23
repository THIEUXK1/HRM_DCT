<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceSyncLog extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'attendance_source_id',
        'company_id',
        'started_at',
        'finished_at',
        'status',
        'total_read',
        'inserted',
        'skipped',
        'unmapped',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'total_read' => 'integer',
            'inserted' => 'integer',
            'skipped' => 'integer',
            'unmapped' => 'integer',
        ];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(AttendanceSource::class, 'attendance_source_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}

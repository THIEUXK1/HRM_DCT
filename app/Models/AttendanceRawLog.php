<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRawLog extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'attendance_source_id',
        'employee_id',
        'employee_code',
        'device_user_id',
        'check_time',
        'raw_payload',
        'unique_hash',
        'status',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'check_time' => 'datetime',
            'raw_payload' => 'array',
        ];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(AttendanceSource::class, 'attendance_source_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}

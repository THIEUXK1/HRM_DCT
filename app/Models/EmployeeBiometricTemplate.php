<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeBiometricTemplate extends Model
{
    protected $fillable = [
        'company_id',
        'employee_id',
        'finger_index',
        'template',
        'source_device_id',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'finger_index' => 'integer',
            'synced_at'    => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function sourceDevice(): BelongsTo
    {
        return $this->belongsTo(AttendanceDevice::class, 'source_device_id');
    }

    /** Trả về binary data từ base64 đã lưu. */
    public function binaryTemplate(): string
    {
        return base64_decode($this->template);
    }

    /** Tên ngón tay theo chỉ số 0–9. */
    public static function fingerName(int $index): string
    {
        return [
            0 => 'Ngón cái trái', 1 => 'Ngón trỏ trái', 2 => 'Ngón giữa trái',
            3 => 'Ngón áp út trái', 4 => 'Ngón út trái',
            5 => 'Ngón cái phải', 6 => 'Ngón trỏ phải', 7 => 'Ngón giữa phải',
            8 => 'Ngón áp út phải', 9 => 'Ngón út phải',
        ][$index] ?? "Ngón {$index}";
    }
}

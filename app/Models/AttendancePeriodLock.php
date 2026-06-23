<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendancePeriodLock extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'period', 'locked_by', 'locked_at',
        'unlocked_by', 'unlocked_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'locked_at' => 'datetime',
            'unlocked_at' => 'datetime',
        ];
    }

    public function isActive(): bool
    {
        return $this->unlocked_at === null;
    }

    public function lockedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function unlockedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'unlocked_by');
    }
}

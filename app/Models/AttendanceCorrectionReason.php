<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceCorrectionReason extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'code', 'name', 'counts_as_forgot_punch', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'counts_as_forgot_punch' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function requests(): HasMany
    {
        return $this->hasMany(AttendanceCorrectionRequest::class, 'correction_reason_id');
    }
}

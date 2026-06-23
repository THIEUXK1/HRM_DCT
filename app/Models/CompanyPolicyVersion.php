<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyPolicyVersion extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'domain',
        'effective_from',
        'snapshot_json',
        'applied_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'snapshot_json' => 'array',
        ];
    }

    public function appliedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by');
    }
}

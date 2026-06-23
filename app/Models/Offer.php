<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Offer extends Model
{
    protected $fillable = [
        'candidate_id', 'salary_base', 'start_date', 'contract_type', 'status', 'letter_notes', 'accepted_at',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'accepted_at' => 'datetime',
            'salary_base' => 'decimal:2',
        ];
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JobPost extends Model
{
    protected $fillable = [
        'recruitment_request_id', 'title', 'job_description', 'channel', 'external_url',
        'status', 'published_at', 'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'date',
            'closed_at' => 'date',
        ];
    }

    public function recruitmentRequest(): BelongsTo
    {
        return $this->belongsTo(RecruitmentRequest::class);
    }

    public function candidates(): HasMany
    {
        return $this->hasMany(Candidate::class);
    }
}

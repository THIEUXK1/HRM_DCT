<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InterviewFeedback extends Model
{
    protected $fillable = [
        'interview_id', 'interviewer_id', 'score', 'feedback', 'recommendation', 'scorecard',
    ];

    protected function casts(): array
    {
        return ['scorecard' => 'array'];
    }

    public function interview(): BelongsTo
    {
        return $this->belongsTo(Interview::class);
    }
}

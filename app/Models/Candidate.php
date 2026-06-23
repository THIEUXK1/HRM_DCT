<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Candidate extends Model
{
    use BelongsToCompany, SoftDeletes, \App\Models\Concerns\BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'company_id', 'job_post_id', 'full_name', 'email', 'phone',
        'source', 'stage', 'expected_salary', 'notes', 'experience_summary', 'skills',
        'employee_id', 'rejected_at',
    ];

    protected function casts(): array
    {
        return [
            'skills' => 'array',
            'expected_salary' => 'decimal:2',
            'rejected_at' => 'datetime',
        ];
    }

    public function jobPost(): BelongsTo
    {
        return $this->belongsTo(JobPost::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function interviews(): HasMany
    {
        return $this->hasMany(Interview::class);
    }

    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(CandidateDocument::class);
    }

    public function isHirable(): bool
    {
        return ! $this->employee_id
            && $this->stage !== 'rejected'
            && $this->offers()->where('status', 'accepted')->exists();
    }
}

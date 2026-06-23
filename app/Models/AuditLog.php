<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'actor_id',
        'actor_name',
        'company_id',
        'tenant_id',
        'entity_type',
        'entity_id',
        'action',
        'action_category',
        'description',
        'field_name',
        'old_value',
        'new_value',
        'ip_address',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ── Relationships ────────────────────────────────────────────────────────
    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    // ── Scopes ───────────────────────────────────────────────────────────────
    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeCategory(Builder $query, string $category): Builder
    {
        return $query->where('action_category', $category);
    }

    public function scopeForEntity(Builder $query, string $type, int $id): Builder
    {
        return $query->where('entity_type', $type)->where('entity_id', $id);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /** Entity class shortname (e.g. App\Models\Employee → Employee) */
    public function getEntityShortNameAttribute(): string
    {
        return class_basename($this->entity_type);
    }

    /** Decode JSON old/new value to array for display */
    public function getOldAttribute(): ?array
    {
        return $this->old_value ? json_decode($this->old_value, true) : null;
    }

    public function getNewAttribute(): ?array
    {
        return $this->new_value ? json_decode($this->new_value, true) : null;
    }
}

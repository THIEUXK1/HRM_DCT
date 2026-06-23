<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkScheduleGroup extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'code', 'name', 'group_type', 'description', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function patterns(): HasMany
    {
        return $this->hasMany(WorkSchedulePattern::class);
    }

    public function employeeSchedules(): HasMany
    {
        return $this->hasMany(EmployeeWorkSchedule::class);
    }
}

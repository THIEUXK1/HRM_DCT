<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'branch_id',
        'department_id',
        'position_id',
        'leave_entitlement_group_id',
        'annual_leave_days_override',
        'employee_code',
        'first_name',
        'last_name',
        'full_name',
        'email',
        'phone',
        'gender',
        'date_of_birth',
        'hire_date',
        'probation_end_date',
        'employment_status',
        'manager_id',
        'work_email',
        'work_phone',
        'national_id',
        'id_card_type',
        'id_card_issue_date',
        'id_card_issue_place',
        'id_card_expiry_date',
        'tax_code',
        'social_insurance_number',
        'health_insurance_card',
        'bhxh_start_date',
        'insurance_salary',
        'pit_dependents_count',
        'union_member',
        'bank_account',
        'bank_name',
        'bank_account_name',
        'bank_branch',
        'address',
        'city',
        'country',
        'nationality',
        'ethnicity',
        'religion',
        'place_of_birth',
        'origin_place',
        'permanent_address',
        'temporary_address',
        'ward',
        'district',
        'province',
        'employment_type',
        'work_location',
        'official_start_date',
        'termination_date',
        'termination_reason',
        'personal_email',
        'old_national_id',
        'bhxh_stop_date',
        'note',
        'is_active',
        'onboarding_completed_at',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'hire_date' => 'date',
            'probation_end_date' => 'date',
            'official_start_date' => 'date',
            'termination_date' => 'date',
            'id_card_issue_date' => 'date',
            'id_card_expiry_date' => 'date',
            'bhxh_start_date' => 'date',
            'bhxh_stop_date' => 'date',
            'insurance_salary' => 'integer',
            'pit_dependents_count' => 'integer',
            'union_member' => 'boolean',
            'is_active' => 'boolean',
            'onboarding_completed_at' => 'datetime',
            'annual_leave_days_override' => 'float',
        ];
    }

    public function dependents()
    {
        return $this->hasMany(EmployeeDependent::class);
    }

    public function activeDependentsCount(): int
    {
        return $this->dependents()->where('is_active', true)->count();
    }

    public function pitDependentsForPayroll(): int
    {
        if ($this->relationLoaded('dependents')) {
            return $this->dependents->where('is_active', true)->count();
        }

        return max(
            (int) $this->pit_dependents_count,
            $this->activeDependentsCount()
        );
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function position()
    {
        return $this->belongsTo(Position::class);
    }

    public function leaveEntitlementGroup()
    {
        return $this->belongsTo(LeaveEntitlementGroup::class);
    }

    public function manager()
    {
        return $this->belongsTo(self::class, 'manager_id');
    }

    public function subordinates()
    {
        return $this->hasMany(self::class, 'manager_id');
    }

    public function profile()
    {
        return $this->hasOne(EmployeeProfile::class);
    }

    public function documents()
    {
        return $this->hasMany(EmployeeDocument::class);
    }

    public function contracts()
    {
        return $this->hasMany(EmploymentContract::class);
    }

    public function onboardingTasks()
    {
        return $this->hasMany(EmployeeOnboardingTask::class);
    }

    public function awards()
    {
        return $this->hasMany(EmployeeAwardDiscipline::class);
    }

    public function transfers()
    {
        return $this->hasMany(EmployeeTransfer::class);
    }

    public function terminations()
    {
        return $this->hasMany(EmployeeTermination::class);
    }

    public function user()
    {
        return $this->hasOne(User::class);
    }
}

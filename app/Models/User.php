<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Services\Security\UserAuthorizationService;
use App\Support\CompanyContext;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles {
        hasRole as protected traitHasRole;
        hasPermissionTo as protected traitHasPermissionTo;
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'employee_id',
        'default_company_id',
        'name',
        'email',
        'password',
        'must_change_password',
        'password_changed_at',
        'punch_account_provisioned_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'api_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'token_expires_at'  => 'datetime',
            'password_changed_at' => 'datetime',
            'punch_account_provisioned_at' => 'datetime',
            'must_change_password' => 'boolean',
            'password'          => 'hashed',
        ];
    }

    public function isTokenExpired(): bool
    {
        if ($this->token_expires_at === null) {
            return false; // legacy tokens without expiry still work
        }

        return $this->token_expires_at->isPast();
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function defaultCompany()
    {
        return $this->belongsTo(Company::class, 'default_company_id');
    }

    public function companies()
    {
        return $this->belongsToMany(Company::class, 'user_companies');
    }

    public function companyRoles()
    {
        return $this->hasMany(UserCompanyRole::class);
    }

    public function isTenantAdmin(): bool
    {
        return $this->roles()->where('name', 'admin')->exists();
    }

    public function hasRole($roles, $guard = null): bool
    {
        if ($this->isTenantAdmin()) {
            return true;
        }

        $companyId = CompanyContext::id();
        $authz = app(UserAuthorizationService::class);

        if ($companyId && $authz->hasCompanyScopedRoles($this, (int) $companyId)) {
            return $authz->userHasRole($this, $roles, (int) $companyId);
        }

        return $this->traitHasRole($roles, $guard);
    }

    public function hasPermissionTo($permission, $guardName = null): bool
    {
        if ($this->isTenantAdmin()) {
            return true;
        }

        $companyId = CompanyContext::id();
        $authz = app(UserAuthorizationService::class);

        if ($companyId && $authz->hasCompanyScopedRoles($this, (int) $companyId)) {
            return $authz->userCan($this, (string) $permission, (int) $companyId);
        }

        return $this->traitHasPermissionTo($permission, $guardName);
    }
}

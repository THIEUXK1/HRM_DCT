<?php

namespace App\Services\Security;

use App\Models\User;
use App\Models\UserCompanyRole;
use App\Support\CompanyContext;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

/**
 * Phân quyền theo công ty: admin toàn tenant + vai trò riêng từng CTTV.
 */
class UserAuthorizationService
{
    /** Vai trò không được gán bởi HR (chỉ admin tập đoàn). */
    private const PROTECTED_ROLES = ['admin'];

    public function isTenantAdmin(User $user): bool
    {
        return $user->roles()->where('name', 'admin')->exists();
    }

    /** @return list<string> */
    public function rolesForCompany(User $user, ?int $companyId): array
    {
        if ($this->isTenantAdmin($user)) {
            return ['admin'];
        }

        if (! $companyId) {
            return $user->getRoleNames()->all();
        }

        $scoped = UserCompanyRole::query()
            ->where('user_id', $user->id)
            ->where('company_id', $companyId)
            ->pluck('role')
            ->unique()
            ->values()
            ->all();

        if ($scoped !== []) {
            return $scoped;
        }

        if (UserCompanyRole::query()->where('user_id', $user->id)->exists()) {
            return [];
        }

        return $user->getRoleNames()->all();
    }

    /** @return list<string> */
    public function permissionsForCompany(User $user, ?int $companyId): array
    {
        if ($this->isTenantAdmin($user)) {
            return $user->getAllPermissions()->pluck('name')->all();
        }

        $roles = $this->rolesForCompany($user, $companyId);
        $permissions = collect();

        foreach ($roles as $roleName) {
            $role = Role::findByName($roleName);
            $permissions = $permissions->merge($role->permissions->pluck('name'));
        }

        return $permissions->unique()->values()->all();
    }

    public function userHasRole(User $user, mixed $roles, ?int $companyId): bool
    {
        // Flatten nested arrays (Spatie's hasAnyRole passes [['role1','role2']] via variadic)
        $roles = collect(is_array($roles) ? $roles : [$roles])
            ->flatten()
            ->map(fn ($r) => is_object($r) ? ($r->name ?? (string) $r) : (string) $r)
            ->all();

        $effective = $this->rolesForCompany($user, $companyId);

        return count(array_intersect($roles, $effective)) > 0;
    }

    public function userCan(User $user, string $permission, ?int $companyId): bool
    {
        if ($this->isTenantAdmin($user)) {
            return true;
        }

        return in_array($permission, $this->permissionsForCompany($user, $companyId), true);
    }

    public function hasCompanyScopedRoles(User $user, int $companyId): bool
    {
        return UserCompanyRole::query()
            ->where('user_id', $user->id)
            ->where('company_id', $companyId)
            ->exists();
    }

    /** @return Collection<int, int> */
    public function accessibleCompanyIds(User $user): Collection
    {
        if ($this->isTenantAdmin($user)) {
            $tenantId = $user->tenant_id ?? CompanyContext::tenantId();

            return \App\Models\Company::query()
                ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
                ->pluck('id');
        }

        $explicit = $user->companies()->pluck('companies.id');
        $employeeCompanyId = $user->employee?->company_id;

        return $explicit
            ->when($user->default_company_id, fn ($c) => $c->push($user->default_company_id))
            ->when($employeeCompanyId, fn ($c) => $c->push($employeeCompanyId))
            ->unique()
            ->filter()
            ->values();
    }

    public function canManageUser(User $actor, User $target): bool
    {
        if ($this->isTenantAdmin($actor)) {
            return true;
        }

        if (! $actor->can('users.manage')) {
            return false;
        }

        $actorCompanies = $this->accessibleCompanyIds($actor);
        $targetCompanies = $this->accessibleCompanyIds($target);

        return $actorCompanies->intersect($targetCompanies)->isNotEmpty()
            || $targetCompanies->isEmpty();
    }

    public function canGrantCompanies(User $actor, array $companyIds): bool
    {
        if ($this->isTenantAdmin($actor)) {
            return true;
        }

        if (! $actor->can('users.manage')) {
            return false;
        }

        $allowed = $this->accessibleCompanyIds($actor);

        return collect($companyIds)->every(fn ($id) => $allowed->contains((int) $id));
    }

    /** @param  list<string>  $roles */
    public function filterAssignableRoles(User $actor, array $roles): array
    {
        $roles = array_values(array_unique(array_filter($roles)));

        if ($this->isTenantAdmin($actor)) {
            return $roles;
        }

        return array_values(array_diff($roles, self::PROTECTED_ROLES));
    }

    /** @return array<string, list<string>> company_id => roles */
    public function companyRolesMap(User $user): array
    {
        $rows = UserCompanyRole::query()
            ->where('user_id', $user->id)
            ->get(['company_id', 'role']);

        $map = [];
        foreach ($rows as $row) {
            $map[(string) $row->company_id][] = $row->role;
        }

        return $map;
    }
}

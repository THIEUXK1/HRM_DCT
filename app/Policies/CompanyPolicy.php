<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CompanyPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasPermissionTo('companies.view');
    }

    public function view(User $user, Company $company): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasPermissionTo('companies.create');
    }

    public function update(User $user, Company $company): bool
    {
        return $user->hasRole('admin') || $user->hasPermissionTo('companies.edit');
    }

    public function delete(User $user, Company $company): bool
    {
        return $user->hasRole('admin') || $user->hasPermissionTo('companies.delete');
    }

    public function applyTemplate(User $user, Company $company): bool
    {
        return $user->hasRole('admin') || $user->can('policy_templates.apply');
    }
}

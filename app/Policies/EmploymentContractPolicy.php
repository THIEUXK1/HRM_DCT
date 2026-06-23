<?php

namespace App\Policies;

use App\Models\EmploymentContract;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class EmploymentContractPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasPermissionTo('employment_contracts.view');
    }

    public function view(User $user, EmploymentContract $employmentContract): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasPermissionTo('employment_contracts.create');
    }

    public function update(User $user, EmploymentContract $employmentContract): bool
    {
        return $user->hasRole('admin') || $user->hasPermissionTo('employment_contracts.edit');
    }
}

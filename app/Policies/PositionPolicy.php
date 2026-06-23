<?php

namespace App\Policies;

use App\Models\Position;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PositionPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasPermissionTo('positions.view');
    }

    public function view(User $user, Position $position): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasPermissionTo('positions.create');
    }

    public function update(User $user, Position $position): bool
    {
        return $user->hasRole('admin') || $user->hasPermissionTo('positions.edit');
    }

    public function delete(User $user, Position $position): bool
    {
        return $user->hasRole('admin') || $user->hasPermissionTo('positions.delete');
    }
}

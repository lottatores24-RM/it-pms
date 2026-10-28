<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('user.viewAny');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $model): bool
    {
        return $user->hasPermissionTo('user.view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('user.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, User $model): bool
    {
        return $user->hasPermissionTo('user.update');
    }

    /**
     * Activating or deactivating an account. Nobody may deactivate themselves,
     * which also guarantees at least one active Administrator remains.
     */
    public function toggleActive(User $user, User $model): bool
    {
        return $user->hasPermissionTo('user.deactivate') && $user->isNot($model);
    }

    /**
     * Changing a user's global role (FR-005). Users cannot change their own
     * role, so an Administrator can never demote the last Administrator.
     */
    public function assignRole(User $user, User $model): bool
    {
        return $user->hasPermissionTo('user.update') && $user->isNot($model);
    }
}

<?php

namespace App\Policies;

use App\Models\User;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('role.viewAny');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Role $role): bool
    {
        return $user->hasPermissionTo('role.view');
    }

    /**
     * The Administrator role always keeps every permission (FR-004), so its
     * permission set is locked; the other built-in roles can be adjusted.
     */
    public function update(User $user, Role $role): bool
    {
        return $user->hasPermissionTo('role.update') && $role->name !== User::ADMIN_ROLE;
    }
}

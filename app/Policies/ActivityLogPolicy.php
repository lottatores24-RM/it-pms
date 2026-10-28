<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

/**
 * Viewing the audit trail (FR-019). Entries are append-only, so there are no
 * update or delete abilities.
 */
class ActivityLogPolicy
{
    /**
     * The global log across every project (Administrator).
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('activity_log.viewAny');
    }

    /**
     * The log of a single project (its Project Manager, or an Administrator).
     */
    public function viewProject(User $user, Project $project): bool
    {
        return $user->hasProjectPermission($project, 'activity_log.view');
    }
}

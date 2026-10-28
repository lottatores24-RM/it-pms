<?php

namespace App\Policies;

use App\Models\Milestone;
use App\Models\Project;
use App\Models\User;

/**
 * Milestone rules (FR-012). Closed projects are read-only.
 */
class MilestonePolicy
{
    public function viewAny(User $user, Project $project): bool
    {
        return $user->hasProjectPermission($project, 'milestone.viewAny');
    }

    public function view(User $user, Milestone $milestone): bool
    {
        return $user->hasProjectPermission($milestone->project, 'milestone.view');
    }

    public function create(User $user, Project $project): bool
    {
        return ! $project->isClosed() && $user->hasProjectPermission($project, 'milestone.create');
    }

    public function update(User $user, Milestone $milestone): bool
    {
        return ! $milestone->project->isClosed() && $user->hasProjectPermission($milestone->project, 'milestone.update');
    }

    public function delete(User $user, Milestone $milestone): bool
    {
        return ! $milestone->project->isClosed() && $user->hasProjectPermission($milestone->project, 'milestone.delete');
    }
}

<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

/**
 * Project-level checks go through User::hasProjectPermission(), so a user's
 * role inside the project (not their global role) decides what they may do.
 */
class ProjectPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('project.viewAny');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Project $project): bool
    {
        return $user->hasProjectPermission($project, 'project.view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('project.create');
    }

    /**
     * Closed projects are read-only (FR-007).
     */
    public function update(User $user, Project $project): bool
    {
        return ! $project->isClosed() && $user->hasProjectPermission($project, 'project.update');
    }

    /**
     * Opening the project's KPI dashboard (FR-020).
     */
    public function viewDashboard(User $user, Project $project): bool
    {
        return $user->hasProjectPermission($project, 'dashboard.view');
    }

    /**
     * Opening the project's Kanban board (FR-017).
     */
    public function viewKanban(User $user, Project $project): bool
    {
        return $user->hasProjectPermission($project, 'kanban.view');
    }

    public function close(User $user, Project $project): bool
    {
        return $project->status->canBeClosed() && $user->hasProjectPermission($project, 'project.close');
    }

    /**
     * The closing report exists once a project is closed and is readable by
     * everyone who can see the project (FR-021).
     */
    public function viewClosingReport(User $user, Project $project): bool
    {
        return $project->isClosed() && $this->view($user, $project);
    }

    /**
     * Only an Administrator holds project.reopen (FR-007).
     */
    public function reopen(User $user, Project $project): bool
    {
        return $project->isClosed() && $user->hasPermissionTo('project.reopen');
    }
}

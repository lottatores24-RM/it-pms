<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;

/**
 * Membership management (FR-008). Closed projects are read-only.
 */
class ProjectMemberPolicy
{
    public function viewAny(User $user, Project $project): bool
    {
        return $user->hasProjectPermission($project, 'member.viewAny');
    }

    public function create(User $user, Project $project): bool
    {
        return ! $project->isClosed() && $user->hasProjectPermission($project, 'member.create');
    }

    public function update(User $user, ProjectMember $member): bool
    {
        return ! $member->project->isClosed() && $user->hasProjectPermission($member->project, 'member.updateRole');
    }

    public function delete(User $user, ProjectMember $member): bool
    {
        return ! $member->project->isClosed() && $user->hasProjectPermission($member->project, 'member.remove');
    }
}

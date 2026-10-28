<?php

namespace App\Policies;

use App\Enums\IssueStatus;
use App\Models\Issue;
use App\Models\Project;
use App\Models\User;

/**
 * Issue rules (FR-015). Project Managers manage every issue; Team Members
 * report issues and move the ones assigned to them up to Resolved. Closed
 * issues and closed projects are read-only.
 */
class IssuePolicy
{
    public function viewAny(User $user, Project $project): bool
    {
        return $user->hasProjectPermission($project, 'issue.viewAny');
    }

    public function view(User $user, Issue $issue): bool
    {
        return $user->hasProjectPermission($issue->project, 'issue.view');
    }

    public function create(User $user, Project $project): bool
    {
        return ! $project->isClosed() && $user->hasProjectPermission($project, 'issue.create');
    }

    /**
     * Editing details, including severity and assignee.
     */
    public function update(User $user, Issue $issue): bool
    {
        return ! $issue->project->isClosed()
            && $issue->status !== IssueStatus::Closed
            && $user->hasProjectPermission($issue->project, 'issue.update');
    }

    /**
     * Assigning someone when reporting a new issue requires the same right.
     */
    public function assign(User $user, Project $project): bool
    {
        return $user->hasProjectPermission($project, 'issue.update');
    }

    /**
     * Closing (Resolved → Closed) is reserved for users who may edit issues.
     */
    public function updateStatus(User $user, Issue $issue, IssueStatus $to): bool
    {
        if ($issue->project->isClosed() || $issue->status === IssueStatus::Closed) {
            return false;
        }

        if ($this->update($user, $issue)) {
            return true;
        }

        return $to !== IssueStatus::Closed
            && $issue->assigned_to === $user->id
            && $user->hasProjectPermission($issue->project, 'issue.updateStatus');
    }

    public function delete(User $user, Issue $issue): bool
    {
        return ! $issue->project->isClosed() && $user->hasProjectPermission($issue->project, 'issue.delete');
    }
}

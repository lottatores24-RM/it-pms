<?php

namespace App\Policies;

use App\Enums\ChangeRequestStatus;
use App\Enums\ProjectRole;
use App\Models\ChangeRequest;
use App\Models\Project;
use App\Models\User;

/**
 * Change request rules (FR-016). Project Managers create, edit and review
 * every request; Team Members submit requests and only see their own.
 * Approved/Rejected requests and closed projects are read-only.
 */
class ChangeRequestPolicy
{
    public function viewAny(User $user, Project $project): bool
    {
        return $user->hasProjectPermission($project, 'change_request.viewAny');
    }

    /**
     * Whether the user sees every request in the project rather than only
     * the ones they submitted. Team Members are limited to their own unless
     * their role was granted review rights.
     */
    public function viewAll(User $user, Project $project): bool
    {
        return $user->projectRole($project) !== ProjectRole::TeamMember
            || $user->hasProjectPermission($project, 'change_request.review');
    }

    public function view(User $user, ChangeRequest $changeRequest): bool
    {
        return $user->hasProjectPermission($changeRequest->project, 'change_request.view')
            && ($changeRequest->requested_by === $user->id || $this->viewAll($user, $changeRequest->project));
    }

    public function create(User $user, Project $project): bool
    {
        return ! $project->isClosed() && $user->hasProjectPermission($project, 'change_request.create');
    }

    /**
     * Editing details is possible until a decision has been made.
     */
    public function update(User $user, ChangeRequest $changeRequest): bool
    {
        return ! $changeRequest->project->isClosed()
            && ! $changeRequest->status->isFinal()
            && $user->hasProjectPermission($changeRequest->project, 'change_request.update');
    }

    /**
     * Moving the request through review (each step needs a justification).
     */
    public function review(User $user, ChangeRequest $changeRequest): bool
    {
        return ! $changeRequest->project->isClosed()
            && ! $changeRequest->status->isFinal()
            && $user->hasProjectPermission($changeRequest->project, 'change_request.review');
    }

    /**
     * Only requests nobody has started reviewing may be deleted; afterwards
     * they are part of the project's change history.
     */
    public function delete(User $user, ChangeRequest $changeRequest): bool
    {
        return ! $changeRequest->project->isClosed()
            && $changeRequest->status === ChangeRequestStatus::Submitted
            && $user->hasProjectPermission($changeRequest->project, 'change_request.delete');
    }
}

<?php

namespace App\Policies;

use App\Enums\IssueStatus;
use App\Models\Attachment;
use App\Models\Issue;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Attachments (FR-018). Every member may download; Project Managers and
 * Team Members upload; Project Managers delete. Closed projects and closed
 * issues accept no new files.
 */
class AttachmentPolicy
{
    /**
     * @param  Model  $target  Project, Task or Issue using HasCollaboration
     */
    public function create(User $user, Model $target): bool
    {
        $project = $target->collaborationProject();

        return ! $project->isClosed()
            && ! ($target instanceof Issue && $target->status === IssueStatus::Closed)
            && $user->hasProjectPermission($project, 'attachment.upload');
    }

    public function download(User $user, Attachment $attachment): bool
    {
        return $user->hasProjectPermission($attachment->project(), 'attachment.download');
    }

    public function delete(User $user, Attachment $attachment): bool
    {
        $project = $attachment->project();

        return ! $project->isClosed() && $user->hasProjectPermission($project, 'attachment.delete');
    }
}

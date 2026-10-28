<?php

namespace App\Policies;

use App\Enums\IssueStatus;
use App\Models\Comment;
use App\Models\Issue;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Comments (FR-018). Viewers only read; authors delete their own comments
 * and Project Managers delete any comment in their project. Closed projects
 * and closed issues accept no new comments.
 */
class CommentPolicy
{
    /**
     * Seeing the comment thread of a project, task or issue.
     *
     * @param  Model  $target  Project, Task or Issue using HasCollaboration
     */
    public function viewAny(User $user, Model $target): bool
    {
        return $user->hasProjectPermission($target->collaborationProject(), 'comment.view');
    }

    /**
     * @param  Model  $target  Project, Task or Issue using HasCollaboration
     */
    public function create(User $user, Model $target): bool
    {
        $project = $target->collaborationProject();

        return ! $project->isClosed()
            && ! ($target instanceof Issue && $target->status === IssueStatus::Closed)
            && $user->hasProjectPermission($project, 'comment.create');
    }

    public function delete(User $user, Comment $comment): bool
    {
        $project = $comment->project();

        if ($project->isClosed()) {
            return false;
        }

        if ($user->hasProjectPermission($project, 'comment.delete')) {
            return true;
        }

        return $comment->user_id === $user->id && $user->hasProjectPermission($project, 'comment.deleteSelf');
    }
}

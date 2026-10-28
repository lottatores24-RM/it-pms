<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;

/**
 * Task rules (FR-009, FR-010, FR-011). Members may view every task in their
 * project; Team Members may only change the status of tasks assigned to them.
 * Closed projects are read-only.
 */
class TaskPolicy
{
    public function viewAny(User $user, Project $project): bool
    {
        return $user->hasProjectPermission($project, 'task.viewAny');
    }

    public function view(User $user, Task $task): bool
    {
        return $user->hasProjectPermission($task->project, 'task.view');
    }

    public function create(User $user, Project $project): bool
    {
        return ! $project->isClosed() && $user->hasProjectPermission($project, 'task.create');
    }

    public function update(User $user, Task $task): bool
    {
        return ! $task->project->isClosed() && $user->hasProjectPermission($task->project, 'task.update');
    }

    public function delete(User $user, Task $task): bool
    {
        return ! $task->project->isClosed() && $user->hasProjectPermission($task->project, 'task.delete');
    }

    /**
     * Dragging a card on the Kanban board (FR-017): kanban.moveTask moves any
     * card, kanban.moveOwnTask only cards assigned to the user.
     */
    public function moveOnKanban(User $user, Task $task): bool
    {
        if ($task->project->isClosed()) {
            return false;
        }

        if ($user->hasProjectPermission($task->project, 'kanban.moveTask')) {
            return true;
        }

        return $task->assigned_to === $user->id
            && $user->hasProjectPermission($task->project, 'kanban.moveOwnTask');
    }

    /**
     * Recording the effort actually spent (FR-022) follows the status rules:
     * editors for every task, Team Members for the tasks assigned to them.
     */
    public function recordEffort(User $user, Task $task): bool
    {
        return $this->updateStatus($user, $task);
    }

    /**
     * Users who may edit tasks can move any task; others only their own.
     * Which transitions are allowed is checked by UpdateTaskStatusRequest.
     */
    public function updateStatus(User $user, Task $task): bool
    {
        if ($this->update($user, $task)) {
            return true;
        }

        return ! $task->project->isClosed()
            && $task->assigned_to === $user->id
            && $user->hasProjectPermission($task->project, 'task.updateStatus');
    }
}

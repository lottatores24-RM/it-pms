<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\ActivityAction;
use App\Enums\TaskStatus;
use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskStatusChanged;
use Illuminate\Support\Facades\DB;

/**
 * Applies an already validated task status transition (FR-011): saves it,
 * writes the audit entry and notifies the project managers and assignee.
 * Shared by the task page and the Kanban board.
 */
trait ChangesTaskStatus
{
    protected function changeTaskStatus(Project $project, Task $task, TaskStatus $to, User $actor): TaskStatus
    {
        $from = $task->status;

        DB::transaction(function () use ($task, $from, $to): void {
            $task->update(['status' => $to]);

            ActivityLog::record(
                ActivityAction::StatusChange,
                $task,
                "Mengubah status task: {$from->value} → {$to->value}",
                ['status' => $from->value],
                ['status' => $to->value],
            );
        });

        $this->notifyUsers(
            [...$project->managers()->get(), $task->assignee],
            new TaskStatusChanged($task, $actor, $from, $to),
        );

        return $from;
    }

    /**
     * Transitions the given user may perform on the task with the given ability.
     *
     * @return list<TaskStatus>
     */
    protected function transitionsFor(User $user, Task $task, string $ability = 'updateStatus'): array
    {
        if (! $user->can($ability, $task)) {
            return [];
        }

        $canReopen = $user->can('update', $task);

        return array_values(array_filter(
            $task->status->allowedTransitions(),
            fn (TaskStatus $to) => $canReopen || ! $task->status->isReopen($to),
        ));
    }
}

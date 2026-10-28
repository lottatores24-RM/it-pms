<?php

namespace App\Notifications;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use Illuminate\Notifications\Notification;

/**
 * A task moved through the workflow (FR-010, FR-019).
 */
class TaskStatusChanged extends Notification
{
    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Task $task,
        public User $actor,
        public TaskStatus $from,
        public TaskStatus $to,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'icon' => 'status',
            'title' => 'Status task berubah',
            'message' => "{$this->actor->name} memindahkan \"{$this->task->title}\" dari {$this->from->value} ke {$this->to->value}.",
            'url' => route('projects.tasks.show', [$this->task->project_id, $this->task->id]),
            'project' => $this->task->project->name,
            'actor' => $this->actor->name,
        ];
    }
}

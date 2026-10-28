<?php

namespace App\Notifications;

use App\Models\Task;
use App\Models\User;
use Illuminate\Notifications\Notification;

/**
 * A task was assigned to the notifiable user (FR-009, FR-019).
 */
class TaskAssigned extends Notification
{
    /**
     * Create a new notification instance.
     */
    public function __construct(public Task $task, public User $actor) {}

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
            'icon' => 'task',
            'title' => 'Task baru untuk Anda',
            'message' => "{$this->actor->name} menugaskan \"{$this->task->title}\" kepada Anda.",
            'url' => route('projects.tasks.show', [$this->task->project_id, $this->task->id]),
            'project' => $this->task->project->name,
            'actor' => $this->actor->name,
        ];
    }
}

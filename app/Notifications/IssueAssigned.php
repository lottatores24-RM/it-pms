<?php

namespace App\Notifications;

use App\Models\Issue;
use App\Models\User;
use Illuminate\Notifications\Notification;

/**
 * An issue was assigned to the notifiable user (FR-015, FR-019).
 */
class IssueAssigned extends Notification
{
    /**
     * Create a new notification instance.
     */
    public function __construct(public Issue $issue, public User $actor) {}

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
            'icon' => 'issue',
            'title' => 'Issue untuk Anda',
            'message' => "{$this->actor->name} menugaskan issue \"{$this->issue->title}\" (severity {$this->issue->severity->value}) kepada Anda.",
            'url' => route('projects.issues.show', [$this->issue->project_id, $this->issue->id]),
            'project' => $this->issue->project->name,
            'actor' => $this->actor->name,
        ];
    }
}

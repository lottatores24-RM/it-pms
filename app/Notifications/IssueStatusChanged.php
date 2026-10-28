<?php

namespace App\Notifications;

use App\Enums\IssueStatus;
use App\Models\Issue;
use App\Models\User;
use Illuminate\Notifications\Notification;

/**
 * An issue moved through its workflow (FR-015, FR-019).
 */
class IssueStatusChanged extends Notification
{
    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Issue $issue,
        public User $actor,
        public IssueStatus $from,
        public IssueStatus $to,
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
            'title' => 'Status issue berubah',
            'message' => "{$this->actor->name} memindahkan issue \"{$this->issue->title}\" dari {$this->from->value} ke {$this->to->value}.",
            'url' => route('projects.issues.show', [$this->issue->project_id, $this->issue->id]),
            'project' => $this->issue->project->name,
            'actor' => $this->actor->name,
        ];
    }
}

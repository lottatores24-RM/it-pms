<?php

namespace App\Notifications;

use App\Models\ChangeRequest;
use App\Models\User;
use Illuminate\Notifications\Notification;

/**
 * A new change request is waiting for the Project Manager's review (FR-016, FR-019).
 */
class ChangeRequestSubmitted extends Notification
{
    /**
     * Create a new notification instance.
     */
    public function __construct(
        public ChangeRequest $changeRequest,
        public User $actor,
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
            'icon' => 'change_request',
            'title' => 'Change request baru',
            'message' => "{$this->actor->name} mengajukan change request \"{$this->changeRequest->title}\" ({$this->changeRequest->priority->value}).",
            'url' => route('projects.change-requests.show', [$this->changeRequest->project_id, $this->changeRequest->id]),
            'project' => $this->changeRequest->project->name,
            'actor' => $this->actor->name,
        ];
    }
}

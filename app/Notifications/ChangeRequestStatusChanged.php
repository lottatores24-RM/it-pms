<?php

namespace App\Notifications;

use App\Enums\ChangeRequestStatus;
use App\Models\ChangeRequest;
use App\Models\User;
use Illuminate\Notifications\Notification;

/**
 * A change request moved through review (FR-016, FR-019).
 */
class ChangeRequestStatusChanged extends Notification
{
    /**
     * Create a new notification instance.
     */
    public function __construct(
        public ChangeRequest $changeRequest,
        public User $actor,
        public ChangeRequestStatus $from,
        public ChangeRequestStatus $to,
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
            'title' => 'Status change request berubah',
            'message' => "{$this->actor->name} memindahkan change request \"{$this->changeRequest->title}\" dari {$this->from->value} ke {$this->to->value}.",
            'url' => route('projects.change-requests.show', [$this->changeRequest->project_id, $this->changeRequest->id]),
            'project' => $this->changeRequest->project->name,
            'actor' => $this->actor->name,
        ];
    }
}

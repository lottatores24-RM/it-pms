<?php

namespace App\Notifications;

use App\Models\Comment;
use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * A new comment was posted on something the user is involved in (FR-018, FR-019).
 */
class CommentAdded extends Notification
{
    /**
     * Create a new notification instance.
     */
    public function __construct(public Comment $comment, public User $actor) {}

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
        $target = $this->comment->commentable;

        return [
            'icon' => 'comment',
            'title' => 'Komentar baru',
            'message' => "{$this->actor->name} berkomentar di {$target->collaborationLabel()}: \"".Str::limit($this->comment->body, 80).'"',
            'url' => $target->collaborationUrl().'#komentar',
            'project' => $target->collaborationProject()->name,
            'actor' => $this->actor->name,
        ];
    }
}

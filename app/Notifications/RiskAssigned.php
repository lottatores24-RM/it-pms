<?php

namespace App\Notifications;

use App\Models\Risk;
use App\Models\User;
use Illuminate\Notifications\Notification;

/**
 * The notifiable user became the owner of a risk (FR-013, FR-019).
 */
class RiskAssigned extends Notification
{
    /**
     * Create a new notification instance.
     */
    public function __construct(public Risk $risk, public User $actor) {}

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
            'icon' => 'risk',
            'title' => 'Anda menjadi pemilik risiko',
            'message' => "{$this->actor->name} menunjuk Anda sebagai pemilik risiko \"{$this->risk->title}\" (level {$this->risk->risk_level->value}).",
            'url' => route('projects.risks.show', [$this->risk->project_id, $this->risk->id]),
            'project' => $this->risk->project->name,
            'actor' => $this->actor->name,
        ];
    }
}

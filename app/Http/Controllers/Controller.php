<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;

abstract class Controller
{
    /**
     * Send an in-app notification (FR-019) to each distinct, active recipient,
     * skipping the user who performed the action.
     *
     * @param  iterable<User|null>  $users
     */
    protected function notifyUsers(iterable $users, Notification $notification): void
    {
        $actorId = auth()->id();

        $recipients = collect($users)
            ->filter()
            ->unique('id')
            ->reject(fn (User $user) => $user->id === $actorId || ! $user->is_active)
            ->values();

        if ($recipients->isNotEmpty()) {
            NotificationFacade::send($recipients, $notification);
        }
    }
}

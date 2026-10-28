<?php

namespace App\Enums;

enum MilestoneStatus: string
{
    case Planned = 'Planned';
    case InProgress = 'In Progress';
    case Completed = 'Completed';
    case Missed = 'Missed';

    /**
     * Allowed status changes (FR-012). Completed and Missed milestones can be
     * reopened, e.g. after rescheduling; leaving Completed needs confirmation.
     *
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Planned => [self::InProgress, self::Missed],
            self::InProgress => [self::Completed, self::Missed],
            self::Completed => [self::InProgress, self::Planned],
            self::Missed => [self::InProgress, self::Planned],
        };
    }

    public function canTransitionTo(self $status): bool
    {
        return $status === $this || in_array($status, $this->allowedTransitions(), true);
    }

    /**
     * Leaving Completed undoes a delivered milestone, so it must be confirmed.
     */
    public function requiresConfirmationTo(self $status): bool
    {
        return $this === self::Completed && $status !== self::Completed;
    }

    /**
     * Statuses that still count as open work.
     */
    public function isOpen(): bool
    {
        return in_array($this, [self::Planned, self::InProgress], true);
    }

    /**
     * Badge colour used in the UI.
     */
    public function color(): string
    {
        return match ($this) {
            self::Planned => 'gray',
            self::InProgress => 'blue',
            self::Completed => 'green',
            self::Missed => 'red',
        };
    }
}

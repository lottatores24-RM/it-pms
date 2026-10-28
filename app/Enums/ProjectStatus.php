<?php

namespace App\Enums;

enum ProjectStatus: string
{
    case Planning = 'Planning';
    case InProgress = 'In Progress';
    case OnHold = 'On Hold';
    case Closed = 'Closed';

    /**
     * Statuses this status may move to through a regular update.
     * Closing and reopening go through their own dedicated actions.
     *
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Planning => [self::InProgress],
            self::InProgress => [self::OnHold],
            self::OnHold => [self::InProgress],
            self::Closed => [],
        };
    }

    public function canTransitionTo(self $status): bool
    {
        return $status === $this || in_array($status, $this->allowedTransitions(), true);
    }

    /**
     * A project is formally closed from In Progress (FR-007 status flow).
     */
    public function canBeClosed(): bool
    {
        return $this === self::InProgress;
    }

    /**
     * Badge colour used in the UI.
     */
    public function color(): string
    {
        return match ($this) {
            self::Planning => 'blue',
            self::InProgress => 'green',
            self::OnHold => 'yellow',
            self::Closed => 'gray',
        };
    }
}

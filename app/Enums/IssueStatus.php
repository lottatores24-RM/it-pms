<?php

namespace App\Enums;

enum IssueStatus: string
{
    case Open = 'Open';
    case InProgress = 'In Progress';
    case Resolved = 'Resolved';
    case Closed = 'Closed';

    /**
     * Allowed transitions (FR-015): Open → In Progress → Resolved → Closed.
     *
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Open => [self::InProgress],
            self::InProgress => [self::Resolved],
            self::Resolved => [self::Closed],
            self::Closed => [],
        };
    }

    public function canTransitionTo(self $status): bool
    {
        return in_array($status, $this->allowedTransitions(), true);
    }

    /**
     * Button label for moving to the given status.
     */
    public function transitionLabel(self $status): string
    {
        return match ($status) {
            self::InProgress => 'Mulai tangani',
            self::Resolved => 'Tandai resolved',
            self::Closed => 'Tutup issue',
            self::Open => 'Buka',
        };
    }

    /**
     * Badge colour used in the UI.
     */
    public function color(): string
    {
        return match ($this) {
            self::Open => 'orange',
            self::InProgress => 'blue',
            self::Resolved => 'green',
            self::Closed => 'gray',
        };
    }
}

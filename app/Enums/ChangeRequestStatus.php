<?php

namespace App\Enums;

enum ChangeRequestStatus: string
{
    case Submitted = 'Submitted';
    case UnderReview = 'Under Review';
    case Approved = 'Approved';
    case Rejected = 'Rejected';

    /**
     * Allowed transitions (FR-016): Submitted → Under Review → Approved | Rejected.
     *
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Submitted => [self::UnderReview],
            self::UnderReview => [self::Approved, self::Rejected],
            self::Approved, self::Rejected => [],
        };
    }

    public function canTransitionTo(self $status): bool
    {
        return in_array($status, $this->allowedTransitions(), true);
    }

    /**
     * Approved and Rejected requests are read-only.
     */
    public function isFinal(): bool
    {
        return $this === self::Approved || $this === self::Rejected;
    }

    /**
     * Button label for moving to the given status.
     */
    public function transitionLabel(self $status): string
    {
        return match ($status) {
            self::UnderReview => 'Mulai review',
            self::Approved => 'Setujui',
            self::Rejected => 'Tolak',
            self::Submitted => 'Ajukan',
        };
    }

    /**
     * Badge colour used in the UI.
     */
    public function color(): string
    {
        return match ($this) {
            self::Submitted => 'orange',
            self::UnderReview => 'blue',
            self::Approved => 'green',
            self::Rejected => 'red',
        };
    }
}

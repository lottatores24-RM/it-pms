<?php

namespace App\Enums;

enum RiskStatus: string
{
    case Open = 'Open';
    case Mitigated = 'Mitigated';
    case Closed = 'Closed';

    /**
     * Allowed transitions (FR-014): Open → Mitigated → Closed, Open → Closed,
     * and a closed risk can be reopened.
     *
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Open => [self::Mitigated, self::Closed],
            self::Mitigated => [self::Closed],
            self::Closed => [self::Open],
        };
    }

    public function canTransitionTo(self $status): bool
    {
        return in_array($status, $this->allowedTransitions(), true);
    }

    /**
     * Button label for moving from this status to the given one.
     */
    public function transitionLabel(self $status): string
    {
        return match ($status) {
            self::Mitigated => 'Tandai termitigasi',
            self::Closed => 'Tutup risiko',
            self::Open => 'Buka kembali',
        };
    }

    /**
     * Badge colour used in the UI.
     */
    public function color(): string
    {
        return match ($this) {
            self::Open => 'orange',
            self::Mitigated => 'blue',
            self::Closed => 'gray',
        };
    }
}

<?php

namespace App\Enums;

enum RiskLevel: string
{
    case Low = 'Low';
    case Medium = 'Medium';
    case High = 'High';
    case Critical = 'Critical';

    /**
     * Derive the level from a probability × impact score (1–9).
     */
    /**
     * Badge colour used in the UI.
     */
    public function color(): string
    {
        return match ($this) {
            self::Low => 'green',
            self::Medium => 'yellow',
            self::High => 'orange',
            self::Critical => 'red',
        };
    }

    /**
     * Background colour for the probability × impact matrix cells.
     */
    public function cellClass(): string
    {
        return match ($this) {
            self::Low => 'bg-green-100 text-green-900',
            self::Medium => 'bg-yellow-100 text-yellow-900',
            self::High => 'bg-orange-200 text-orange-900',
            self::Critical => 'bg-red-300 text-red-950',
        };
    }

    public static function fromScore(int $score): self
    {
        return match (true) {
            $score >= 9 => self::Critical,
            $score >= 4 => self::High,
            $score === 3 => self::Medium,
            default => self::Low,
        };
    }
}

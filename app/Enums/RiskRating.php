<?php

namespace App\Enums;

/**
 * Rating scale used for a risk's probability and impact.
 */
enum RiskRating: string
{
    case Low = 'Low';
    case Medium = 'Medium';
    case High = 'High';

    public function weight(): int
    {
        return match ($this) {
            self::Low => 1,
            self::Medium => 2,
            self::High => 3,
        };
    }
}

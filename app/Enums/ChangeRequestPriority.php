<?php

namespace App\Enums;

enum ChangeRequestPriority: string
{
    case Low = 'Low';
    case Medium = 'Medium';
    case High = 'High';

    /**
     * Badge colour used in the UI.
     */
    public function color(): string
    {
        return match ($this) {
            self::Low => 'gray',
            self::Medium => 'blue',
            self::High => 'orange',
        };
    }
}

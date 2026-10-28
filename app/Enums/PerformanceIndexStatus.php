<?php

namespace App\Enums;

/**
 * Reading of an EVM performance index (FR-022): above 1 is better than
 * planned, below 1 is worse. Compared at the two decimals shown in the UI.
 */
enum PerformanceIndexStatus: string
{
    case Above = 'above';
    case OnTarget = 'on_target';
    case Below = 'below';

    public static function fromIndex(?float $index): ?self
    {
        if ($index === null) {
            return null;
        }

        return match (round($index, 2) <=> 1.0) {
            1 => self::Above,
            0 => self::OnTarget,
            -1 => self::Below,
        };
    }

    /**
     * Interpretation of SPI = EV / PV.
     */
    public function scheduleLabel(): string
    {
        return match ($this) {
            self::Above => 'Lebih cepat dari jadwal',
            self::OnTarget => 'Sesuai jadwal',
            self::Below => 'Terlambat dari jadwal',
        };
    }

    /**
     * Interpretation of CPI = EV / AC.
     */
    public function costLabel(): string
    {
        return match ($this) {
            self::Above => 'Effort lebih hemat dari rencana',
            self::OnTarget => 'Effort sesuai rencana',
            self::Below => 'Effort melebihi rencana',
        };
    }

    /**
     * Badge colour; always shown together with the label text.
     */
    public function color(): string
    {
        return match ($this) {
            self::Above, self::OnTarget => 'green',
            self::Below => 'red',
        };
    }
}

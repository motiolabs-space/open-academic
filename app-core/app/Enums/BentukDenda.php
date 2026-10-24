<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How a late fee grows per period: a fixed amount, or a share of what is still
 * owed on the principal.
 */
enum BentukDenda: string
{
    case Nominal = 'nominal';
    case Persen = 'persen';

    public function label(): string
    {
        return match ($this) {
            self::Nominal => 'Nominal tetap',
            self::Persen => 'Persen dari sisa tagihan',
        };
    }
}

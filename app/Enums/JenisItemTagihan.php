<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Whether an invoice line adds to the bill or takes away from it.
 *
 * The sign of `nominal` already says this, and the column exists anyway: every
 * screen groups charges apart from reductions, and every query that wants one
 * without the other would otherwise filter on `nominal < 0` — a condition that
 * reads as an accident rather than a category.
 */
enum JenisItemTagihan: string
{
    case Tagihan = 'tagihan';
    case Potongan = 'potongan';

    public function label(): string
    {
        return match ($this) {
            self::Tagihan => 'Komponen Tagihan',
            self::Potongan => 'Potongan',
        };
    }
}

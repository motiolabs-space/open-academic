<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Settlement state of a student invoice (tagihan).
 * Derived from paid amount vs. total — never set by hand.
 */
enum InvoiceStatus: string
{
    case BelumBayar = 'belum_bayar';
    case Sebagian = 'sebagian';
    case Lunas = 'lunas';
    case Batal = 'batal';

    public function label(): string
    {
        return match ($this) {
            self::BelumBayar => 'Belum Bayar',
            self::Sebagian => 'Sebagian',
            self::Lunas => 'Lunas',
            self::Batal => 'Batal',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::BelumBayar => 'danger',
            self::Sebagian => 'warning',
            self::Lunas => 'success',
            self::Batal => 'neutral',
        };
    }

    public static function fromAmounts(int $total, int $paid): self
    {
        return match (true) {
            $paid <= 0 => self::BelumBayar,
            $paid >= $total => self::Lunas,
            default => self::Sebagian,
        };
    }
}

<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The unit of lateness a fee is charged per.
 */
enum PeriodeDenda: string
{
    case Hari = 'hari';
    case Minggu = 'minggu';

    public function label(): string
    {
        return match ($this) {
            self::Hari => 'hari',
            self::Minggu => 'minggu',
        };
    }

    /** Periods begun after `$hariTerlambat` days of lateness; a week just started counts whole. */
    public function dariHari(int $hariTerlambat): int
    {
        if ($hariTerlambat <= 0) {
            return 0;
        }

        return match ($this) {
            self::Hari => $hariTerlambat,
            self::Minggu => intdiv($hariTerlambat + 6, 7),
        };
    }
}

<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Arithmetic on PDDIKTI term codes (`20261` odd, `20262` even, `20263` short).
 *
 * Short terms fold into the even semester of their year, as PDDIKTI does when
 * it counts study length. Same convention as Mahasiswa::semesterDiAsal.
 */
final class KodeSemester
{
    public static function sah(?string $kode): bool
    {
        return $kode !== null && preg_match('/^\d{4}[123]$/', $kode) === 1;
    }

    public static function antara(?string $kode): bool
    {
        return self::sah($kode) && str_ends_with((string) $kode, '3');
    }

    /**
     * Regular semesters from one term to another, both counted. Zero when
     * either end is unknown, so a missing record is visible as such rather
     * than as a plausible length.
     */
    public static function rentang(?string $dari, ?string $sampai): int
    {
        if (!self::sah($dari) || !self::sah($sampai)) {
            return 0;
        }

        return max(0, self::urutan((string) $sampai) - self::urutan((string) $dari) + 1);
    }

    private static function urutan(string $kode): int
    {
        return (int) substr($kode, 0, 4) * 2 + min(2, (int) substr($kode, 4, 1));
    }
}

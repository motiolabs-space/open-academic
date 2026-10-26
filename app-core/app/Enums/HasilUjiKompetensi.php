<?php

declare(strict_types=1);

namespace App\Enums;

/** Outcome of one sitting of the national competency examination. */
enum HasilUjiKompetensi: string
{
    case Lulus = 'lulus';
    case TidakLulus = 'tidak_lulus';

    public function label(): string
    {
        return match ($this) {
            self::Lulus => 'Lulus',
            self::TidakLulus => 'Belum Lulus',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Lulus => 'success',
            self::TidakLulus => 'warning',
        };
    }
}

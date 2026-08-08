<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Whether an examination is upcoming, done, or called off.
 *
 * Separate from HasilUjian on purpose: a cancelled defence has no verdict, and
 * storing "not yet examined" as a verdict is how a student ends up recorded as
 * having failed a session that never happened.
 */
enum StatusUjian: string
{
    case Dijadwalkan = 'dijadwalkan';
    case Selesai = 'selesai';
    case Dibatalkan = 'dibatalkan';

    public function label(): string
    {
        return match ($this) {
            self::Dijadwalkan => 'Dijadwalkan',
            self::Selesai => 'Selesai',
            self::Dibatalkan => 'Dibatalkan',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Dijadwalkan => 'info',
            self::Selesai => 'success',
            self::Dibatalkan => 'neutral',
        };
    }
}

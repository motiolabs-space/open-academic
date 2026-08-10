<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The life of a performance period.
 *
 * Locking is one-way. A period that can be reopened is a period whose figures
 * can be revised after they were reported, and the whole point of freezing is
 * that the report and the record still agree a year later.
 */
enum StatusPeriodeKinerja: string
{
    case Draf = 'draf';
    case Berjalan = 'berjalan';
    case Dikunci = 'dikunci';

    public function label(): string
    {
        return match ($this) {
            self::Draf => 'Draf',
            self::Berjalan => 'Berjalan',
            self::Dikunci => 'Dikunci',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Draf => 'neutral',
            self::Berjalan => 'info',
            self::Dikunci => 'success',
        };
    }

    /** Whether targets and the objective tree may still be edited. */
    public function dapatDiubah(): bool
    {
        return $this !== self::Dikunci;
    }

    /** Whether new check-ins may be recorded. */
    public function menerimaCapaian(): bool
    {
        return $this === self::Berjalan;
    }
}

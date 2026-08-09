<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Which of the two ledgers a point row belongs to.
 *
 * They are never added together. A student with 100 achievement points and 100
 * violation points is not a student with zero — and any code that computes such
 * a number has decided, on the campus's behalf, that a competition win cancels
 * a forged signature.
 */
enum JenisPoin: string
{
    case Prestasi = 'prestasi';
    case Pelanggaran = 'pelanggaran';

    public function label(): string
    {
        return match ($this) {
            self::Prestasi => 'Prestasi & kegiatan',
            self::Pelanggaran => 'Pelanggaran',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Prestasi => 'success',
            self::Pelanggaran => 'danger',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $j): array => [$j->value => $j->label()])
            ->all();
    }
}

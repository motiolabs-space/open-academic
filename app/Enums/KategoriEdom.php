<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The dimensions a teaching evaluation reports on.
 *
 * Fixed rather than free-form, because the categories are what makes results
 * comparable — between lecturers, between programmes, and between years. A
 * campus that invents a new category each period ends up with averages that
 * cannot be placed beside each other, which is most of the value gone.
 *
 * The wording of individual questions stays free; only the buckets are fixed.
 */
enum KategoriEdom: string
{
    case Materi = 'materi';
    case Penyampaian = 'penyampaian';
    case Disiplin = 'disiplin';
    case Penilaian = 'penilaian';
    case Sikap = 'sikap';

    public function label(): string
    {
        return match ($this) {
            self::Materi => 'Penguasaan Materi',
            self::Penyampaian => 'Kemampuan Menyampaikan',
            self::Disiplin => 'Kedisiplinan & Kehadiran',
            self::Penilaian => 'Keadilan Penilaian',
            self::Sikap => 'Sikap & Keterbukaan',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            fn (array $carry, self $case): array => $carry + [$case->value => $case->label()],
            [],
        );
    }
}

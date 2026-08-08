<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The four elements a lecturer's semester is divided into.
 *
 * Three are the Tri Dharma; the fourth is everything that keeps a campus running
 * and is not any of them. A report is assessed element by element, not as one
 * total, which is why a lecturer carrying twenty credits of teaching and nothing
 * else can still fail.
 */
enum UnsurBkd: string
{
    case Pendidikan = 'pendidikan';
    case Penelitian = 'penelitian';
    case Pengabdian = 'pengabdian';
    case Penunjang = 'penunjang';

    public function label(): string
    {
        return match ($this) {
            self::Pendidikan => 'Pendidikan & Pengajaran',
            self::Penelitian => 'Penelitian',
            self::Pengabdian => 'Pengabdian kepada Masyarakat',
            self::Penunjang => 'Penunjang',
        };
    }

    /**
     * Whether Open Academic can derive this element from what it already holds.
     *
     * Only teaching. Classes, supervision, examining, and advising are all
     * recorded here as a by-product of running the semester, so a lecturer
     * should never retype them. The other three never pass through a SIAKAD at
     * all, and pretending otherwise would produce a report with confident
     * zeroes in it.
     */
    public function terhitungOtomatis(): bool
    {
        return $this === self::Pendidikan;
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

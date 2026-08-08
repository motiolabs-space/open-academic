<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Which examination in the final-project sequence this is.
 *
 * Campuses differ on how many they run — one defence, or a proposal seminar and
 * a defence, or all three. None of them is mandatory here: a campus schedules
 * the ones it holds and the rest simply never exist as rows.
 *
 * Only Sidang can conclude a project. A proposal seminar can be failed and
 * repeated without the work ending.
 */
enum JenisUjian: string
{
    case Proposal = 'proposal';
    case Hasil = 'hasil';
    case Sidang = 'sidang';

    public function label(): string
    {
        return match ($this) {
            self::Proposal => 'Seminar Proposal',
            self::Hasil => 'Seminar Hasil',
            self::Sidang => 'Sidang Akhir',
        };
    }

    /** Whether passing this examination finishes the project. */
    public function menutup(): bool
    {
        return $this === self::Sidang;
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

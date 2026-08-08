<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Whether recognised credit has actually been granted.
 *
 * Only Disetujui counts anywhere — towards credits, towards the transcript,
 * towards graduation. A proposal that has not been decided is somebody's opinion
 * about equivalence, and a transcript that included it would be asserting
 * something the campus has not agreed to.
 */
enum StatusKonversi: string
{
    case Diajukan = 'diajukan';
    case Disetujui = 'disetujui';
    case Ditolak = 'ditolak';

    public function label(): string
    {
        return match ($this) {
            self::Diajukan => 'Menunggu Keputusan',
            self::Disetujui => 'Diakui',
            self::Ditolak => 'Ditolak',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Diajukan => 'warning',
            self::Disetujui => 'success',
            self::Ditolak => 'danger',
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

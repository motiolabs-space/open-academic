<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What kind of certificate a lecturer holds.
 *
 * Serdos is separated from everything else because it is the only one with a
 * payment attached, and the only one a BKD report is assessed *for*. A campus
 * that stores it in the same bucket as an industry course certificate ends up
 * unable to answer the one question the ministry asks.
 */
enum JenisSertifikasi: string
{
    case Serdos = 'serdos';
    case Profesi = 'profesi';
    case Kompetensi = 'kompetensi';
    case Lainnya = 'lainnya';

    public function label(): string
    {
        return match ($this) {
            self::Serdos => 'Sertifikat Pendidik (Serdos)',
            self::Profesi => 'Sertifikat Profesi',
            self::Kompetensi => 'Sertifikat Kompetensi',
            self::Lainnya => 'Lainnya',
        };
    }

    /**
     * Whether holding this obliges the lecturer to report BKD.
     *
     * Only Serdos does. Reporting is the condition of the allowance, so a
     * campus that requires BKD from everybody is imposing paperwork the
     * regulation does not.
     */
    public function mewajibkanBkd(): bool
    {
        return $this === self::Serdos;
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

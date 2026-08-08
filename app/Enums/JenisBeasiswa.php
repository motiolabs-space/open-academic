<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Who bears the cost of a scholarship.
 *
 * The distinction is not cosmetic. An internal scheme is money the campus
 * chooses not to collect; an external one is money somebody else owes it. Both
 * lower what the student pays, and only one of them leaves a receivable behind.
 *
 * Collecting from the sponsor happens in the finance system, not here. What this
 * records is who a reduction was granted on behalf of — so the campus can always
 * answer that question about its own books.
 */
enum JenisBeasiswa: string
{
    case Internal = 'internal';
    case Eksternal = 'eksternal';

    public function label(): string
    {
        return match ($this) {
            self::Internal => 'Internal (ditanggung kampus)',
            self::Eksternal => 'Eksternal (ditanggung penyandang)',
        };
    }

    /** Whether a sponsor must be named. */
    public function perluPenyandang(): bool
    {
        return $this === self::Eksternal;
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

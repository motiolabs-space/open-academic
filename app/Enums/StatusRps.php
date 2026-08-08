<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where a teaching plan has got to.
 *
 * `Berlaku` is terminal for editing. A plan that could still change after the
 * semester started would mean a mark recorded against outcome CPL-02 in week
 * four might belong to CPL-05 by week twelve — and nobody would be able to say
 * when it changed. Revising a plan in force means publishing a new version.
 */
enum StatusRps: string
{
    case Draft = 'draft';
    case Berlaku = 'berlaku';
    case Diarsipkan = 'diarsipkan';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::Berlaku => 'Berlaku',
            self::Diarsipkan => 'Diarsipkan',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Draft => 'neutral',
            self::Berlaku => 'success',
            self::Diarsipkan => 'info',
        };
    }

    public function dapatDisunting(): bool
    {
        return $this === self::Draft;
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

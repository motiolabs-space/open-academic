<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How far an activity reached.
 *
 * The axis IKU 5 is reported along, and the biggest single multiplier in most
 * BKD rubrics — which is exactly why it is a fixed enum rather than free text.
 * A campus where one lecturer writes "internasional" and another writes
 * "international conference" cannot count either.
 */
enum TingkatKegiatan: string
{
    case Lokal = 'lokal';
    case Nasional = 'nasional';
    case Internasional = 'internasional';

    public function label(): string
    {
        return match ($this) {
            self::Lokal => 'Lokal / Institusi',
            self::Nasional => 'Nasional',
            self::Internasional => 'Internasional',
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

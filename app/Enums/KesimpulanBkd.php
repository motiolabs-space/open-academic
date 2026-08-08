<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What the assessors concluded.
 *
 * Three outcomes, and the middle one carries the weight. "Memenuhi sebagian"
 * exists because a lecturer can clear the total while failing an element —
 * twenty credits of teaching and no research is a real and common shape — and
 * collapsing that into a plain pass would hide the only finding the report was
 * meant to surface.
 */
enum KesimpulanBkd: string
{
    case Memenuhi = 'memenuhi';
    case MemenuhiSebagian = 'memenuhi_sebagian';
    case TidakMemenuhi = 'tidak_memenuhi';

    public function label(): string
    {
        return match ($this) {
            self::Memenuhi => 'Memenuhi',
            self::MemenuhiSebagian => 'Memenuhi Sebagian',
            self::TidakMemenuhi => 'Tidak Memenuhi',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Memenuhi => 'success',
            self::MemenuhiSebagian => 'warning',
            self::TidakMemenuhi => 'danger',
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

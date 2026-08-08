<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How a question is answered.
 *
 * Two types only. A scale produces a number that can be averaged; free text
 * produces the thing people actually read — and the thing that de-anonymises a
 * respondent in a small class, which is why it is released under stricter rules
 * than the numbers beside it.
 */
enum TipeJawabanEdom: string
{
    case Skala = 'skala';
    case Teks = 'teks';

    public function label(): string
    {
        return match ($this) {
            self::Skala => 'Skala 1–5',
            self::Teks => 'Isian Bebas',
        };
    }

    public function skala(): bool
    {
        return $this === self::Skala;
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

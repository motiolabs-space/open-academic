<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How one question is answered.
 *
 * Three, and deliberately no more. Every extra type is another branch in the
 * form, the validator, and the aggregation — and the fourth one campuses ask
 * for is always expressible as one of these.
 */
enum TipePertanyaan: string
{
    /** 1–5. Aggregated as a mean. */
    case Skala = 'skala';

    /** One of a fixed list. Aggregated as counts. */
    case Pilihan = 'pilihan';

    /** Free text. Never aggregated, only read. */
    case Teks = 'teks';

    public function label(): string
    {
        return match ($this) {
            self::Skala => 'Skala 1–5',
            self::Pilihan => 'Pilihan',
            self::Teks => 'Isian bebas',
        };
    }

    public function berangka(): bool
    {
        return $this === self::Skala;
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $t): array => [$t->value => $t->label()])->all();
    }
}

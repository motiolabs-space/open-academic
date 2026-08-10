<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where a measure's realisation comes from.
 *
 * The single most consequential column in the performance module. A measure
 * whose realisation is *computed* cannot be polished before a review — not
 * because a permission forbids it, but because its value never arrives from a
 * form in the first place.
 */
enum SumberRealisasi: string
{
    /** Read from data this application already holds. Never editable. */
    case Dihitung = 'dihitung';

    /** Typed by the owning unit. Evidence expected. */
    case Dilaporkan = 'dilaporkan';

    /** Arrives from another system over Campus Bridge. */
    case Eksternal = 'eksternal';

    public function label(): string
    {
        return match ($this) {
            self::Dihitung => 'Dihitung dari data',
            self::Dilaporkan => 'Dilaporkan unit',
            self::Eksternal => 'Dari sistem lain',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Dihitung => 'success',
            self::Dilaporkan => 'warning',
            self::Eksternal => 'info',
        };
    }

    /** Whether a person may type this measure's realisation at all. */
    public function bolehDiketik(): bool
    {
        return $this === self::Dilaporkan;
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $s): array => [$s->value => $s->label()])
            ->all();
    }
}

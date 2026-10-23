<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How urgently a ticket should be picked up.
 *
 * Set by the applicant on filing and correctable by staff on disposition — a
 * student marks everything urgent the week before registration closes, and the
 * person who actually knows the queue is the one who should rank it.
 */
enum PrioritasTiket: string
{
    case Rendah = 'rendah';
    case Normal = 'normal';
    case Tinggi = 'tinggi';
    case Mendesak = 'mendesak';

    public function label(): string
    {
        return match ($this) {
            self::Rendah => 'Rendah',
            self::Normal => 'Normal',
            self::Tinggi => 'Tinggi',
            self::Mendesak => 'Mendesak',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Rendah => 'neutral',
            self::Normal => 'info',
            self::Tinggi => 'warning',
            self::Mendesak => 'danger',
        };
    }

    /** Sort weight: higher first in a queue. */
    public function bobot(): int
    {
        return match ($this) {
            self::Rendah => 0,
            self::Normal => 1,
            self::Tinggi => 2,
            self::Mendesak => 3,
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

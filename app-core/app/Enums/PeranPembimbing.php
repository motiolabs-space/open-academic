<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * A supervisor's standing on one final project.
 *
 * Utama is the one accountable for the work and the one whose sign-off gates a
 * defence. Pendamping supports, and a project may have none, one, or several.
 */
enum PeranPembimbing: string
{
    case Utama = 'utama';
    case Pendamping = 'pendamping';

    public function label(): string
    {
        return match ($this) {
            self::Utama => 'Pembimbing Utama',
            self::Pendamping => 'Pembimbing Pendamping',
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

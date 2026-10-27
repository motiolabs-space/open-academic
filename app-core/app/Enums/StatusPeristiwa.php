<?php

declare(strict_types=1);

namespace App\Enums;

/** Whether a calendar entry written by the registrar is visible outside the staff portal. */
enum StatusPeristiwa: string
{
    case Draf = 'draf';
    case Terbit = 'terbit';

    public function label(): string
    {
        return __('kalender.status.'.$this->value);
    }

    public function tone(): string
    {
        return match ($this) {
            self::Draf => 'neutral',
            self::Terbit => 'success',
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

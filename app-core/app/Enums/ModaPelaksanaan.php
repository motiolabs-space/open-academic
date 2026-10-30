<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How a contest was run. Hybrid counts as on-site: SIMKATMAWA treats any
 * contest with an on-site final the same as a fully on-site one.
 */
enum ModaPelaksanaan: string
{
    case Daring = 'daring';
    case Luring = 'luring';

    public function label(): string
    {
        return __('simkatmawa.moda.'.$this->value);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $c): array => [$c->value => $c->label()])
            ->all();
    }
}

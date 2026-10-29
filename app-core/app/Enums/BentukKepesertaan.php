<?php

declare(strict_types=1);

namespace App\Enums;

/** Whether a student competed alone or as part of a team. */
enum BentukKepesertaan: string
{
    case Individu = 'individu';
    case Kelompok = 'kelompok';

    public function label(): string
    {
        return __('simkatmawa.bentuk.'.$this->value);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $c): array => [$c->value => $c->label()])
            ->all();
    }
}

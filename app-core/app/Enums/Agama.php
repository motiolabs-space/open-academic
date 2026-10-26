<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Religion as recorded on an identity document.
 *
 * The backing values are the *local* codes stored in `agama_kode`, the same
 * ones the demo mapping table pairs with PDDIKTI's `id_agama`. They happen to
 * equal the national codes on a stock installation, but the mapper still goes
 * through `feeder_mappings` — a campus whose reference pull returns different
 * codes changes the mapping, not this enum.
 */
enum Agama: string
{
    case Islam = '1';
    case Kristen = '2';
    case Katolik = '3';
    case Hindu = '4';
    case Buddha = '5';
    case Khonghucu = '6';
    case Lainnya = '99';

    public function label(): string
    {
        return __('pmb.agama.'.$this->value);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $agama): array => [$agama->value => $agama->label()])
            ->all();
    }
}

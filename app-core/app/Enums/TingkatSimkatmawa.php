<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The level a contest or recognition was held at, as SIMKATMAWA grades it.
 *
 * Kept apart from TingkatKegiatan and the points catalogue's own levels:
 * SIMKATMAWA separates district from provincial contests and scores them
 * differently, which neither older vocabulary can express.
 */
enum TingkatSimkatmawa: string
{
    case KabupatenKota = 'kabupaten_kota';
    case Provinsi = 'provinsi';
    case Nasional = 'nasional';
    case Internasional = 'internasional';

    public function label(): string
    {
        return __('simkatmawa.tingkat.'.$this->value);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $c): array => [$c->value => $c->label()])
            ->all();
    }
}

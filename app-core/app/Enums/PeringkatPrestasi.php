<?php

declare(strict_types=1);

namespace App\Enums;

/** The place won, from first place down to taking part. */
enum PeringkatPrestasi: string
{
    case Juara1 = 'juara_1';
    case Juara2 = 'juara_2';
    case Juara3 = 'juara_3';
    case Harapan1 = 'harapan_1';
    case Harapan2 = 'harapan_2';
    case Harapan3 = 'harapan_3';
    case Apresiasi = 'apresiasi';
    case Peserta = 'peserta';

    public function label(): string
    {
        return __('simkatmawa.peringkat.'.$this->value);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $c): array => [$c->value => $c->label()])
            ->all();
    }
}

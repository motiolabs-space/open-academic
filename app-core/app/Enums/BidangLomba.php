<?php

declare(strict_types=1);

namespace App\Enums;

/** The field a contest belongs to, in SIMKATMAWA's grouping. */
enum BidangLomba: string
{
    case RisetStem = 'riset_stem';
    case RisetSsh = 'riset_ssh';
    case SeniBudaya = 'seni_budaya';
    case Olahraga = 'olahraga';
    case MinatKhusus = 'minat_khusus';

    public function label(): string
    {
        return __('simkatmawa.bidang.'.$this->value);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $c): array => [$c->value => $c->label()])
            ->all();
    }
}

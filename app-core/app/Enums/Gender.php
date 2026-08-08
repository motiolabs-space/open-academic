<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Biological sex as recorded on identity documents.
 * Backing values are the PDDIKTI "jenis_kelamin" codes (L / P).
 */
enum Gender: string
{
    case LakiLaki = 'L';
    case Perempuan = 'P';

    public function label(): string
    {
        return match ($this) {
            self::LakiLaki => 'Laki-laki',
            self::Perempuan => 'Perempuan',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return [
            self::LakiLaki->value => self::LakiLaki->label(),
            self::Perempuan->value => self::Perempuan->label(),
        ];
    }
}

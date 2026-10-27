<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where a field-programme group is placed: a village for KKN, a partner
 * institution (company, agency, school) for PKL.
 */
enum JenisLokasiKegiatan: string
{
    case Desa = 'desa';
    case Instansi = 'instansi';

    public function label(): string
    {
        return __('kegiatan_lapangan.jenis_lokasi.'.$this->value);
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

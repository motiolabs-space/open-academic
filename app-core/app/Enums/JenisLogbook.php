<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What a field-programme log entry is: a day's record, a weekly report, or the
 * closing report the field supervisor marks the student on.
 */
enum JenisLogbook: string
{
    case Harian = 'harian';
    case Mingguan = 'mingguan';
    case LaporanAkhir = 'laporan_akhir';

    public function label(): string
    {
        return __('kegiatan_lapangan.jenis_logbook.'.$this->value);
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

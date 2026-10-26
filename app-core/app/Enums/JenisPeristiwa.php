<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What kind of date an entry on the academic calendar is.
 *
 * Three kinds are written on the calendar itself (libur, kegiatan, batas); every
 * other kind is read from the table that owns the date, and `manual()` is the
 * line between them. The calendar never stores a KRS window or an exam — it
 * shows the one the gate actually reads.
 */
enum JenisPeristiwa: string
{
    case Libur = 'libur';
    case Kegiatan = 'kegiatan';
    case Batas = 'batas';

    case Perkuliahan = 'perkuliahan';
    case Krs = 'krs';
    case PerubahanKrs = 'perubahan_krs';
    case Nilai = 'nilai';
    case Ujian = 'ujian';
    case Edom = 'edom';
    case Yudisium = 'yudisium';
    case Wisuda = 'wisuda';
    case Pmb = 'pmb';

    public function label(): string
    {
        return __('kalender.jenis.'.$this->value);
    }

    /** Where the date lives — shown beside derived entries so nobody edits the wrong screen. */
    public function sumber(): string
    {
        return __('kalender.sumber.'.$this->value);
    }

    public function manual(): bool
    {
        return in_array($this, self::kasusManual(), true);
    }

    public function tone(): string
    {
        return match ($this) {
            self::Libur => 'danger',
            self::Batas => 'warning',
            self::Kegiatan, self::Edom => 'info',
            self::Ujian => 'gold',
            self::Yudisium, self::Wisuda => 'success',
            default => 'neutral',
        };
    }

    /** @return list<self> */
    public static function kasusManual(): array
    {
        return [self::Libur, self::Kegiatan, self::Batas];
    }

    /**
     * @param list<self>|null $kasus
     * @return array<string, string>
     */
    public static function options(?array $kasus = null): array
    {
        return array_reduce(
            $kasus ?? self::cases(),
            fn (array $carry, self $case): array => $carry + [$case->value => $case->label()],
            [],
        );
    }
}

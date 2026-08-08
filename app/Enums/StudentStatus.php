<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Per-term enrolment status of a student (status kuliah).
 *
 * Backing values mirror the PDDIKTI "id_stat_mhs" codes used by Neo Feeder in
 * AktivitasKuliahMahasiswa. Confirm against the pulled reference table
 * feeder_ref_status_mahasiswa before a production sync — institutions
 * occasionally run older Feeder builds with a narrower code set.
 */
enum StudentStatus: string
{
    case Aktif = 'A';
    case Cuti = 'C';
    case NonAktif = 'N';
    case Lulus = 'L';
    case DropOut = 'D';
    case Keluar = 'K';
    case GantiProdi = 'G';

    public function label(): string
    {
        return match ($this) {
            self::Aktif => 'Aktif',
            self::Cuti => 'Cuti',
            self::NonAktif => 'Non-Aktif',
            self::Lulus => 'Lulus',
            self::DropOut => 'Drop Out',
            self::Keluar => 'Keluar',
            self::GantiProdi => 'Ganti Program Studi',
        };
    }

    /** Tailwind token name driving the status chip colour. */
    public function tone(): string
    {
        return match ($this) {
            self::Aktif => 'success',
            self::Cuti, self::NonAktif => 'warning',
            self::Lulus => 'info',
            self::DropOut, self::Keluar, self::GantiProdi => 'danger',
        };
    }

    /** Whether the student may fill a KRS in this state. */
    public function canEnroll(): bool
    {
        return $this === self::Aktif;
    }

    /** Whether the student has permanently left the institution. */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Lulus, self::DropOut, self::Keluar], true);
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

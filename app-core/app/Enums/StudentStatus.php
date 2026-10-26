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
 *
 * MenungguUkom is the exception: its value is a local code with no PDDIKTI
 * counterpart assumed. It reaches Feeder only through feeder_mappings, and a
 * campus that has not mapped it gets a validation error, not a guess.
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

    /*
     * Academically complete, waiting on the national competency examination.
     *
     * Health programmes (wajib_ukom) do not graduate anyone on coursework
     * alone: the examination is part of the degree. Between a confirmed
     * yudisium and a passing result the student is neither Aktif — they take
     * no courses — nor Lulus, which would let PDDIKTI and the diploma-number
     * service treat as finished a degree that is not.
     */
    case MenungguUkom = 'U';

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
            self::MenungguUkom => 'Menunggu Uji Kompetensi',
        };
    }

    /** Tailwind token name driving the status chip colour. */
    public function tone(): string
    {
        return match ($this) {
            self::Aktif => 'success',
            self::Cuti, self::NonAktif => 'warning',
            self::Lulus, self::MenungguUkom => 'info',
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

    /**
     * Whether the backing value is a code of our own rather than a PDDIKTI
     * one, so it must never be sent to Feeder as-is when the mapping is
     * missing.
     */
    public function kodeLokal(): bool
    {
        return $this === self::MenungguUkom;
    }

    /**
     * Statuses whose terms do not count towards the maximum study period or
     * the evaluation checkpoints.
     *
     * Leave is a pause by definition. Waiting for the competency examination
     * is not studying either: a nurse who passed every course and is waiting
     * for the national sitting must not drift towards a drop-out warning
     * while the calendar runs.
     *
     * @return array<int, self>
     */
    public static function tidakDihitungMasaStudi(): array
    {
        return [self::Cuti, self::MenungguUkom];
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

<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How a student's registration was replaced or reopened (ROADMAP #55).
 *
 * PDDIKTI keys everything a student does on their registration — the
 * enrolment of one person into one programme under one NIM. Two of these
 * events end that registration and start another, which is a new student
 * row here as it is a new registration there; the third reopens the same
 * one. None of them overwrites the old NIM.
 */
enum JenisRegistrasiUlang: string
{
    /** Internal programme transfer: new NIM in another programme; the old registration closes. */
    case PindahProdi = 'pindah_prodi';

    /** Readmission after leaving or drop-out: new NIM; the old registration stays closed. */
    case MasukKembali = 'masuk_kembali';

    /** The same registration reopened: same NIM, back to Aktif. */
    case AktifKembali = 'aktif_kembali';

    public function label(): string
    {
        return __('registrasi_ulang.jenis.'.$this->value);
    }

    /** Whether the event starts a new registration — a new NIM and a new student row. */
    public function registrasiBaru(): bool
    {
        return $this !== self::AktifKembali;
    }

    /** The PDDIKTI enrolment kind the new registration is reported with. */
    public function jenisDaftar(): ?JenisDaftar
    {
        return match ($this) {
            self::PindahProdi => JenisDaftar::PindahProdi,
            self::MasukKembali => JenisDaftar::MasukKembali,
            self::AktifKembali => null,
        };
    }

    /**
     * The statuses the old registration may be in.
     *
     * A transfer moves a student who is still studying; readmission and
     * reactivation follow someone who left. Drop-outs only where the campus
     * allows it (`academic.registrasi_ulang.izinkan_dari_do`). A graduate
     * starting another degree is a new intake through PMB, not this.
     *
     * @return array<int, StudentStatus>
     */
    public function statusAsal(): array
    {
        $keluar = config('academic.registrasi_ulang.izinkan_dari_do')
            ? [StudentStatus::Keluar, StudentStatus::DropOut]
            : [StudentStatus::Keluar];

        return match ($this) {
            self::PindahProdi => [StudentStatus::Aktif, StudentStatus::Cuti, StudentStatus::NonAktif],
            self::MasukKembali, self::AktifKembali => $keluar,
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $j): array => [$j->value => $j->label()])->all();
    }
}

<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Admission funnel stage of a PMB applicant (calon mahasiswa).
 *
 * mendaftar -> verifikasi -> seleksi -> lulus/tidak_lulus -> daftar_ulang
 *           -> mahasiswa (account provisioned, NIM issued)
 */
enum ApplicantStatus: string
{
    case Mendaftar = 'mendaftar';
    case Verifikasi = 'verifikasi';
    case Seleksi = 'seleksi';
    case Lulus = 'lulus';
    case TidakLulus = 'tidak_lulus';
    case DaftarUlang = 'daftar_ulang';
    case Mahasiswa = 'mahasiswa';
    case Batal = 'batal';

    public function label(): string
    {
        return match ($this) {
            self::Mendaftar => 'Mendaftar',
            self::Verifikasi => 'Verifikasi Berkas',
            self::Seleksi => 'Seleksi',
            self::Lulus => 'Lulus Seleksi',
            self::TidakLulus => 'Tidak Lulus',
            self::DaftarUlang => 'Registrasi Ulang',
            self::Mahasiswa => 'Menjadi Mahasiswa',
            self::Batal => 'Batal / Mengundurkan Diri',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Mendaftar, self::Verifikasi, self::Seleksi => 'neutral',
            self::Lulus, self::DaftarUlang => 'warning',
            self::Mahasiswa => 'success',
            self::TidakLulus, self::Batal => 'danger',
        };
    }

    /** Position in the admission funnel, used for the funnel chart. */
    public function funnelStep(): int
    {
        return match ($this) {
            self::Mendaftar => 1,
            self::Verifikasi, self::Seleksi => 2,
            self::Lulus => 3,
            self::DaftarUlang => 4,
            self::Mahasiswa => 5,
            self::TidakLulus, self::Batal => 0,
        };
    }

    /** An applicant may only be converted into a student from this state. */
    public function canBeProvisioned(): bool
    {
        return $this === self::DaftarUlang;
    }
}

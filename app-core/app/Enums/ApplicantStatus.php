<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Admission funnel stage of a PMB applicant (calon mahasiswa).
 *
 * draf -> mendaftar -> verifikasi -> seleksi -> lulus/tidak_lulus
 *      -> daftar_ulang -> mahasiswa (account provisioned, NIM issued)
 *
 * `draf` exists only for applicants who register themselves online: the form
 * is still being filled and nothing has been submitted. It is deliberately
 * outside every count that reads "pendaftar" — the dashboard funnel, LKPS
 * selectivity, the PDDIKTI per-programme scale — because an account somebody
 * opened and abandoned is not an applicant.
 *
 * `mendaftar` means submitted and, for a wave with a form fee, waiting for
 * that fee; `verifikasi` means paid and waiting for the documents to be
 * checked.
 */
enum ApplicantStatus: string
{
    case Draf = 'draf';
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
            self::Draf => 'Mengisi Formulir',
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
            self::Draf, self::Mendaftar, self::Verifikasi, self::Seleksi => 'neutral',
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
            self::Draf, self::TidakLulus, self::Batal => 0,
        };
    }

    /**
     * Whether a move from this stage to another is a legitimate event.
     *
     * Status here is a history, not a field: every move is logged, and some
     * of them (an announcement, a NIM) cannot be taken back. Listing the legal
     * edges once means a controller cannot invent a new one — a rejected
     * applicant silently becoming a student, say — by writing a column.
     *
     * A decision may be corrected in either direction until registration;
     * withdrawal is possible from anywhere short of becoming a student.
     */
    public function bolehMenujuKe(self $tujuan): bool
    {
        return in_array($tujuan, match ($this) {
            self::Draf => [self::Mendaftar, self::Verifikasi, self::Batal],
            self::Mendaftar => [self::Verifikasi, self::Seleksi, self::Lulus, self::TidakLulus, self::Batal],
            // Back to Mendaftar only when the fee payment that moved it here is
            // reversed by the cashier.
            self::Verifikasi => [self::Mendaftar, self::Seleksi, self::Lulus, self::TidakLulus, self::Batal],
            self::Seleksi => [self::Lulus, self::TidakLulus, self::Batal],
            self::Lulus => [self::TidakLulus, self::DaftarUlang, self::Mahasiswa, self::Batal],
            self::TidakLulus => [self::Lulus, self::Batal],
            self::DaftarUlang => [self::Mahasiswa, self::Batal],
            self::Mahasiswa, self::Batal => [],
        }, true);
    }

    /** Whether the selection outcome is settled one way or the other. */
    public function sudahDiputuskan(): bool
    {
        return in_array($this, [self::Lulus, self::TidakLulus, self::DaftarUlang, self::Mahasiswa], true);
    }

    /** An applicant may only be converted into a student from this state. */
    public function canBeProvisioned(): bool
    {
        return $this === self::DaftarUlang;
    }
}

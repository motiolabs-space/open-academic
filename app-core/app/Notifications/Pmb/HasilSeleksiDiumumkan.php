<?php

declare(strict_types=1);

namespace App\Notifications\Pmb;

use App\Enums\ApplicantStatus;

/**
 * The selection outcome, sent when the wave is announced — or, for a
 * decision taken after that, when it is taken.
 *
 * Worded from the status at the moment the worker sends it. The model
 * travels as an identifier and is re-read, so a decision corrected between
 * queueing and sending is reported as corrected.
 */
class HasilSeleksiDiumumkan extends NotifikasiPmb
{
    public function judul(object $penerima): string
    {
        return $this->lulus()
            ? __('pmb.notifikasi.lulus_judul')
            : __('pmb.notifikasi.tidak_lulus_judul');
    }

    public function ringkasan(object $penerima): string
    {
        return $this->lulus()
            ? __('pmb.notifikasi.lulus_ringkasan', [
                'nomor' => $this->pendaftar->nomor_pendaftaran,
                'prodi' => (string) $this->pendaftar->prodiDiterima?->nama,
            ])
            : __('pmb.notifikasi.tidak_lulus_ringkasan', [
                'nomor' => $this->pendaftar->nomor_pendaftaran,
                'gelombang' => (string) $this->pendaftar->gelombang?->nama,
            ]);
    }

    public function tone(): string
    {
        return $this->lulus() ? 'success' : 'neutral';
    }

    private function lulus(): bool
    {
        return in_array($this->pendaftar->status, [
            ApplicantStatus::Lulus, ApplicantStatus::DaftarUlang, ApplicantStatus::Mahasiswa,
        ], true);
    }
}

<?php

declare(strict_types=1);

namespace App\Notifications\Pmb;

/**
 * Registration done: the applicant has a NIM.
 *
 * Carries the NIM, never the temporary password. That is handed over by the
 * registrar in person, as for every other route into the student portal; a
 * password in an inbox outlives the account's first sign-in by years.
 */
class MenjadiMahasiswa extends NotifikasiPmb
{
    public function judul(object $penerima): string
    {
        return __('pmb.notifikasi.mahasiswa_judul');
    }

    public function ringkasan(object $penerima): string
    {
        return __('pmb.notifikasi.mahasiswa_ringkasan', [
            'nomor' => $this->pendaftar->nomor_pendaftaran,
            'nim' => (string) $this->pendaftar->mahasiswa?->nim,
        ]);
    }

    public function tone(): string
    {
        return 'success';
    }
}

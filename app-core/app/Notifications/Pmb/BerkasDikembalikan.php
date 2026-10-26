<?php

declare(strict_types=1);

namespace App\Notifications\Pmb;

use App\Models\Pmb\PmbBerkas;

/**
 * A document the desk could not accept, and why.
 *
 * Without this the applicant learns of it only by logging in, which most do
 * not do while they believe they are waiting on the campus.
 */
class BerkasDikembalikan extends NotifikasiPmb
{
    public function __construct(public readonly PmbBerkas $berkas)
    {
        parent::__construct($berkas->pendaftar);
    }

    public function judul(object $penerima): string
    {
        return __('pmb.notifikasi.berkas_judul');
    }

    public function ringkasan(object $penerima): string
    {
        return __('pmb.notifikasi.berkas_ringkasan', [
            'jenis' => (string) (config('berkas.pmb.'.$this->berkas->jenis) ?? $this->berkas->jenis),
            'nomor' => $this->pendaftar->nomor_pendaftaran,
            'catatan' => (string) $this->berkas->catatan,
        ]);
    }

    public function tone(): string
    {
        return 'warning';
    }
}

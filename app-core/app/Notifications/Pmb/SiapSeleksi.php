<?php

declare(strict_types=1);

namespace App\Notifications\Pmb;

/** Documents checked and fee settled: the exam card can be printed. */
class SiapSeleksi extends NotifikasiPmb
{
    public function judul(object $penerima): string
    {
        return __('pmb.notifikasi.seleksi_judul');
    }

    public function ringkasan(object $penerima): string
    {
        return __('pmb.notifikasi.seleksi_ringkasan', ['nomor' => $this->pendaftar->nomor_pendaftaran]);
    }

    public function tone(): string
    {
        return 'success';
    }
}

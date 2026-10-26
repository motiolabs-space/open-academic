<?php

declare(strict_types=1);

namespace App\Notifications\Pmb;

use App\Models\Pmb\PmbPembayaran;
use App\Support\Format;

/** The receipt for a form fee the cashier recorded. */
class PembayaranFormulirDiterima extends NotifikasiPmb
{
    public function __construct(public readonly PmbPembayaran $pembayaran)
    {
        parent::__construct($pembayaran->pendaftar);
    }

    public function judul(object $penerima): string
    {
        return __('pmb.notifikasi.pembayaran_judul');
    }

    public function ringkasan(object $penerima): string
    {
        return __('pmb.notifikasi.pembayaran_ringkasan', [
            'nominal' => Format::bulat($this->pembayaran->nominal),
            'nomor' => $this->pendaftar->nomor_pendaftaran,
            'sisa' => Format::bulat($this->pendaftar->sisaFormulir()),
        ]);
    }

    public function tone(): string
    {
        return 'success';
    }
}

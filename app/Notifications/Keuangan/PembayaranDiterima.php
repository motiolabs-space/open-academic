<?php

declare(strict_types=1);

namespace App\Notifications\Keuangan;

use App\Enums\KategoriNotifikasi;
use App\Models\Keuangan\Pembayaran;
use App\Notifications\Notifikasi;
use App\Support\Format;

/**
 * A payment has been recorded.
 *
 * The receipt. Worth sending even when the payment was made in person, because
 * it is the student's evidence that the office recorded what they handed over.
 */
class PembayaranDiterima extends Notifikasi
{
    public function __construct(public readonly Pembayaran $pembayaran) {}

    public function kategori(): KategoriNotifikasi
    {
        return KategoriNotifikasi::Keuangan;
    }

    public function judul(object $penerima): string
    {
        return 'Pembayaran diterima';
    }

    public function ringkasan(object $penerima): string
    {
        $tagihan = $this->pembayaran->tagihan;
        $sisa = max(0, (int) $tagihan->total - (int) $tagihan->terbayar);

        return sprintf(
            'Pembayaran %s untuk tagihan %s tercatat. %s',
            Format::rupiah((int) $this->pembayaran->nominal),
            $tagihan->nomor,
            $sisa > 0
                ? 'Sisa tagihan '.Format::rupiah($sisa).'.'
                : 'Tagihan ini lunas.',
        );
    }

    public function tautan(object $penerima): ?string
    {
        return route('mahasiswa.tagihan');
    }

    public function tone(): string
    {
        return 'success';
    }

    public function ajakan(): string
    {
        return 'Lihat Tagihan';
    }
}

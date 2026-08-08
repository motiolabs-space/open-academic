<?php

declare(strict_types=1);

namespace App\Notifications\Keuangan;

use App\Enums\KategoriNotifikasi;
use App\Models\Keuangan\Tagihan;
use App\Notifications\Notifikasi;
use App\Support\Format;

/**
 * A bill went down.
 *
 * Worth telling somebody about for a reason beyond good news: a student who does
 * not know their invoice changed will pay the old amount, and the overpayment
 * then has to be chased back out of the system by hand.
 *
 * The overpayment case is stated outright when it has already happened.
 */
class PotonganDiberikan extends Notifikasi
{
    public function __construct(public readonly Tagihan $tagihan) {}

    public function kategori(): KategoriNotifikasi
    {
        return KategoriNotifikasi::Keuangan;
    }

    public function judul(object $penerima): string
    {
        return 'Tagihan Anda berkurang';
    }

    public function ringkasan(object $penerima): string
    {
        $lebih = $this->tagihan->kelebihanBayar();

        $pesan = sprintf(
            'Potongan %s diberikan pada tagihan %s. Sisa yang harus dibayar %s.',
            Format::rupiah($this->tagihan->totalPotongan()),
            $this->tagihan->nomor,
            Format::rupiah($this->tagihan->sisa()),
        );

        return $lebih > 0
            ? $pesan.' Pembayaran Anda melebihi tagihan sebesar '.Format::rupiah($lebih)
                .'; hubungi bagian keuangan untuk pengembalian atau pemindahan ke semester berikutnya.'
            : $pesan;
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

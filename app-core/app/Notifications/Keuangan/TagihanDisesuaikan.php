<?php

declare(strict_types=1);

namespace App\Notifications\Keuangan;

use App\Enums\KategoriNotifikasi;
use App\Models\Keuangan\Tagihan;
use App\Notifications\Notifikasi;
use App\Support\Format;

/**
 * The term invoice changed because the approved study plan did.
 *
 * A student who added a class during the revision window owes more, and must
 * not learn that from a late fee.
 */
class TagihanDisesuaikan extends Notifikasi
{
    public function __construct(
        public readonly Tagihan $tagihan,
        public readonly int $selisih,
    ) {}

    public function kategori(): KategoriNotifikasi
    {
        return KategoriNotifikasi::Keuangan;
    }

    public function judul(object $penerima): string
    {
        return __('keuangan.krs.notifikasi_judul');
    }

    public function ringkasan(object $penerima): string
    {
        return __('keuangan.krs.notifikasi_ringkasan', [
            'nomor' => $this->tagihan->nomor,
            'total' => Format::rupiah((int) $this->tagihan->total),
            'selisih' => ($this->selisih < 0 ? '−' : '+').Format::rupiah(abs($this->selisih)),
            'tanggal' => Format::tanggal($this->tagihan->jatuh_tempo),
        ]);
    }

    public function tautan(object $penerima): ?string
    {
        return route('mahasiswa.tagihan');
    }

    public function ajakan(): string
    {
        return __('keuangan.krs.notifikasi_ajakan');
    }
}

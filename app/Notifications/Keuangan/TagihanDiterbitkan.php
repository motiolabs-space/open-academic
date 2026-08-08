<?php

declare(strict_types=1);

namespace App\Notifications\Keuangan;

use App\Enums\KategoriNotifikasi;
use App\Models\Keuangan\Tagihan;
use App\Notifications\Notifikasi;
use App\Support\Format;

/** A new invoice is outstanding. */
class TagihanDiterbitkan extends Notifikasi
{
    public function __construct(public readonly Tagihan $tagihan) {}

    public function kategori(): KategoriNotifikasi
    {
        return KategoriNotifikasi::Keuangan;
    }

    public function judul(object $penerima): string
    {
        return 'Tagihan baru: '.$this->tagihan->keterangan;
    }

    public function ringkasan(object $penerima): string
    {
        return sprintf(
            'Tagihan %s sebesar %s jatuh tempo %s.',
            $this->tagihan->nomor,
            Format::rupiah((int) $this->tagihan->total),
            $this->tagihan->jatuh_tempo->translatedFormat('j F Y'),
        );
    }

    public function tautan(object $penerima): ?string
    {
        return route('mahasiswa.tagihan');
    }

    public function ajakan(): string
    {
        return 'Lihat Tagihan';
    }
}

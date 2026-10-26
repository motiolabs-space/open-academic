<?php

declare(strict_types=1);

namespace App\Notifications\Kemahasiswaan;

use App\Enums\KategoriNotifikasi;
use App\Models\Kemahasiswaan\UjiKompetensi;
use App\Notifications\Notifikasi;
use App\Support\Format;

/**
 * A sitting was recorded as not passed. A passing one is announced by
 * KelulusanDitetapkan instead, because that is what it is.
 */
class UjiKompetensiBelumLulus extends Notifikasi
{
    public function __construct(public readonly UjiKompetensi $uji) {}

    public function kategori(): KategoriNotifikasi
    {
        return KategoriNotifikasi::Kemahasiswaan;
    }

    public function judul(object $penerima): string
    {
        return __('ukom.notifikasi.belum_lulus_judul');
    }

    public function ringkasan(object $penerima): string
    {
        return __('ukom.notifikasi.belum_lulus_ringkasan', [
            'tanggal' => Format::tanggal($this->uji->tanggal_ujian),
        ]);
    }

    public function tone(): string
    {
        return 'warning';
    }
}

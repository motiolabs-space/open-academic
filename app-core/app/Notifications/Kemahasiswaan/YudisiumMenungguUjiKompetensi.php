<?php

declare(strict_types=1);

namespace App\Notifications\Kemahasiswaan;

use App\Enums\KategoriNotifikasi;
use App\Models\Kemahasiswaan\Yudisium;
use App\Notifications\Notifikasi;
use App\Support\Format;

/**
 * The academic part of the degree is done; the competency examination is not.
 *
 * Its own message rather than KelulusanDitetapkan: telling a nursing student
 * "your graduation has been confirmed" while PDDIKTI still lists them as
 * waiting is how they find out at the diploma counter.
 */
class YudisiumMenungguUjiKompetensi extends Notifikasi
{
    public function __construct(public readonly Yudisium $yudisium) {}

    public function kategori(): KategoriNotifikasi
    {
        return KategoriNotifikasi::Kemahasiswaan;
    }

    public function judul(object $penerima): string
    {
        return __('ukom.notifikasi.menunggu_judul');
    }

    public function ringkasan(object $penerima): string
    {
        return __('ukom.notifikasi.menunggu_ringkasan', [
            'ipk' => Format::angka($this->yudisium->ipk),
        ]);
    }

    public function tautan(object $penerima): ?string
    {
        return route('mahasiswa.transkrip');
    }

    public function tone(): string
    {
        return 'info';
    }

    public function ajakan(): string
    {
        return 'Lihat Transkrip';
    }
}

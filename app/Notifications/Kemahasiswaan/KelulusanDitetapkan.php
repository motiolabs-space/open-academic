<?php

declare(strict_types=1);

namespace App\Notifications\Kemahasiswaan;

use App\Enums\KategoriNotifikasi;
use App\Models\Kemahasiswaan\Yudisium;
use App\Notifications\Notifikasi;
use App\Support\Format;

/** Graduation has been confirmed. */
class KelulusanDitetapkan extends Notifikasi
{
    public function __construct(public readonly Yudisium $yudisium) {}

    public function kategori(): KategoriNotifikasi
    {
        return KategoriNotifikasi::Kemahasiswaan;
    }

    public function judul(object $penerima): string
    {
        return 'Kelulusan Anda telah ditetapkan';
    }

    public function ringkasan(object $penerima): string
    {
        return sprintf(
            'Yudisium ditetapkan dengan IPK %s, predikat %s, total %d SKS.%s',
            Format::angka($this->yudisium->ipk),
            $this->yudisium->predikat,
            (int) $this->yudisium->total_sks,
            $this->yudisium->nomor_sk !== null ? ' Nomor SK: '.$this->yudisium->nomor_sk.'.' : '',
        );
    }

    public function tautan(object $penerima): ?string
    {
        return route('mahasiswa.transkrip');
    }

    public function tone(): string
    {
        return 'success';
    }

    public function ajakan(): string
    {
        return 'Lihat Transkrip';
    }
}

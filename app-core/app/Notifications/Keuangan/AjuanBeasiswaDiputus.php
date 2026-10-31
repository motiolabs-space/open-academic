<?php

declare(strict_types=1);

namespace App\Notifications\Keuangan;

use App\Enums\KategoriNotifikasi;
use App\Enums\StatusAjuanBeasiswa;
use App\Models\Keuangan\BeasiswaAjuan;
use App\Notifications\Notifikasi;

/**
 * A scholarship application was granted or refused.
 *
 * Finance, so mandatory in-app: a granted award changes what the student is
 * billed, and a refused one is why they still owe the full amount.
 */
class AjuanBeasiswaDiputus extends Notifikasi
{
    public function __construct(public readonly BeasiswaAjuan $ajuan) {}

    public function kategori(): KategoriNotifikasi
    {
        return KategoriNotifikasi::Keuangan;
    }

    public function judul(object $penerima): string
    {
        return __('swalayan.beasiswa.notifikasi.judul_'.$this->ajuan->status->value, [
            'skema' => $this->ajuan->periode->beasiswa->nama,
        ]);
    }

    public function ringkasan(object $penerima): string
    {
        return __('swalayan.beasiswa.notifikasi.ringkasan_'.$this->ajuan->status->value, [
            'periode' => $this->ajuan->periode->nama,
            'catatan' => $this->ajuan->catatan_keputusan ?? '—',
        ]);
    }

    public function tautan(object $penerima): ?string
    {
        return route('mahasiswa.beasiswa');
    }

    public function tone(): string
    {
        return $this->ajuan->status === StatusAjuanBeasiswa::Ditetapkan ? 'success' : 'warning';
    }
}

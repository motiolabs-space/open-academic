<?php

declare(strict_types=1);

namespace App\Notifications\Kemahasiswaan;

use App\Enums\KategoriNotifikasi;
use App\Models\Kemahasiswaan\PoinMahasiswa;
use App\Notifications\Notifikasi;

/**
 * An achievement the student reported was verified or refused.
 *
 * Only sent for rows the student reported themselves: staff-recorded rows have
 * nobody waiting on an answer.
 */
class LaporanPrestasiDiputus extends Notifikasi
{
    public function __construct(public readonly PoinMahasiswa $poin) {}

    public function kategori(): KategoriNotifikasi
    {
        return KategoriNotifikasi::Kemahasiswaan;
    }

    public function judul(object $penerima): string
    {
        return __('swalayan.prestasi.notifikasi.judul_'.$this->kunci());
    }

    public function ringkasan(object $penerima): string
    {
        return __('swalayan.prestasi.notifikasi.ringkasan_'.$this->kunci(), [
            'judul' => $this->poin->judul,
            'poin' => $this->poin->poin,
            'alasan' => $this->poin->alasan_tolak ?? '—',
        ]);
    }

    public function tautan(object $penerima): ?string
    {
        return route('mahasiswa.poin');
    }

    public function tone(): string
    {
        return $this->poin->is_verified ? 'success' : 'warning';
    }

    private function kunci(): string
    {
        return $this->poin->is_verified ? 'diverifikasi' : 'ditolak';
    }
}

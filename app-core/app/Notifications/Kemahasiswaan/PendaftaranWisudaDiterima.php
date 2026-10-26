<?php

declare(strict_types=1);

namespace App\Notifications\Kemahasiswaan;

use App\Enums\KategoriNotifikasi;
use App\Models\Kemahasiswaan\WisudaPeserta;
use App\Notifications\Notifikasi;
use App\Support\Format;

/**
 * The graduate is on a ceremony list — by their own hand or the office's.
 *
 * Sent on both paths: a graduate the office registered in bulk is the one who
 * most needs to learn the date, and has no other reason to look.
 */
class PendaftaranWisudaDiterima extends Notifikasi
{
    public function __construct(public readonly WisudaPeserta $peserta) {}

    public function kategori(): KategoriNotifikasi
    {
        return KategoriNotifikasi::Kemahasiswaan;
    }

    public function judul(object $penerima): string
    {
        return __('wisuda.notifikasi.terdaftar_judul');
    }

    public function ringkasan(object $penerima): string
    {
        $periode = $this->peserta->periode;

        return __('wisuda.notifikasi.terdaftar_ringkasan', [
            'periode' => $periode->nama,
            'tanggal' => Format::tanggal($periode->tanggal),
            'lokasi' => $periode->lokasi ?: '—',
            'urut' => $this->peserta->nomor_urut,
        ]);
    }

    public function tautan(object $penerima): ?string
    {
        return route('mahasiswa.wisuda');
    }

    public function tone(): string
    {
        return 'success';
    }

    public function ajakan(): string
    {
        return __('wisuda.notifikasi.ajakan');
    }
}

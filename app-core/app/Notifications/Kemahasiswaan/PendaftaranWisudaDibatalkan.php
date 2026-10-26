<?php

declare(strict_types=1);

namespace App\Notifications\Kemahasiswaan;

use App\Enums\KategoriNotifikasi;
use App\Models\Kemahasiswaan\WisudaPeriode;
use App\Notifications\Notifikasi;

/** The graduate is no longer on a ceremony list. */
class PendaftaranWisudaDibatalkan extends Notifikasi
{
    public function __construct(public readonly WisudaPeriode $periode) {}

    public function kategori(): KategoriNotifikasi
    {
        return KategoriNotifikasi::Kemahasiswaan;
    }

    public function judul(object $penerima): string
    {
        return __('wisuda.notifikasi.batal_judul');
    }

    public function ringkasan(object $penerima): string
    {
        return __('wisuda.notifikasi.batal_ringkasan', ['periode' => $this->periode->nama]);
    }

    public function tautan(object $penerima): ?string
    {
        return route('mahasiswa.wisuda');
    }

    public function tone(): string
    {
        return 'warning';
    }

    public function ajakan(): string
    {
        return __('wisuda.notifikasi.ajakan');
    }
}

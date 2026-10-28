<?php

declare(strict_types=1);

namespace App\Notifications\Kemahasiswaan;

use App\Enums\KategoriNotifikasi;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Kemahasiswaan\RiwayatDosenWali;
use App\Notifications\Notifikasi;

/**
 * A student's academic advisor was assigned or changed.
 *
 * The student is always told: the next KRS goes to somebody else, and a plan
 * already submitted has moved with it. The new advisor is told only when such
 * a plan exists — otherwise the advisee list on their portal says enough.
 */
class DosenWaliDitetapkan extends Notifikasi
{
    public function __construct(public readonly RiwayatDosenWali $riwayat) {}

    public function kategori(): KategoriNotifikasi
    {
        return KategoriNotifikasi::Akademik;
    }

    public function judul(object $penerima): string
    {
        if (!$penerima instanceof Mahasiswa) {
            return __('dosen_wali.notifikasi.judul_dosen');
        }

        return __($this->riwayat->dosen_lama_id === null
            ? 'dosen_wali.notifikasi.judul_mahasiswa'
            : 'dosen_wali.notifikasi.judul_mahasiswa_ganti');
    }

    public function ringkasan(object $penerima): string
    {
        $dosen = $this->riwayat->dosenBaru->namaLengkap();

        if ($penerima instanceof Mahasiswa) {
            return __($this->riwayat->krs_dialihkan > 0
                ? 'dosen_wali.notifikasi.ringkasan_mahasiswa_krs'
                : 'dosen_wali.notifikasi.ringkasan_mahasiswa', ['dosen' => $dosen]);
        }

        return __('dosen_wali.notifikasi.ringkasan_dosen', [
            'mahasiswa' => $this->riwayat->mahasiswa->nama,
            'nim' => $this->riwayat->mahasiswa->nim,
            'jumlah' => $this->riwayat->krs_dialihkan,
        ]);
    }

    public function tautan(object $penerima): ?string
    {
        return $penerima instanceof Mahasiswa
            ? route('mahasiswa.krs')
            : route('dosen.persetujuan-krs');
    }

    public function ajakan(): string
    {
        return __('dosen_wali.notifikasi.ajakan');
    }
}

<?php

declare(strict_types=1);

namespace App\Notifications\Akademik;

use App\Enums\JenisUjianKelas;
use App\Enums\KategoriNotifikasi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Dosen;
use App\Notifications\Notifikasi;

/**
 * A term's mid-term or final timetable has been published.
 *
 * One message per person per publication, carrying how many of their sessions
 * it covers — not one per session. See JadwalUjianService::umumkanTerbit().
 */
class JadwalUjianTerbit extends Notifikasi
{
    public function __construct(
        public readonly TahunAkademik $term,
        public readonly JenisUjianKelas $jenis,
        public readonly int $jumlah,
    ) {}

    public function kategori(): KategoriNotifikasi
    {
        return KategoriNotifikasi::Akademik;
    }

    public function judul(object $penerima): string
    {
        return __('jadwal_ujian.notifikasi.terbit_judul', [
            'jenis' => $this->jenis->singkatan(),
            'term' => $this->term->nama,
        ]);
    }

    public function ringkasan(object $penerima): string
    {
        return __($penerima instanceof Mahasiswa
            ? 'jadwal_ujian.notifikasi.terbit_mahasiswa'
            : 'jadwal_ujian.notifikasi.terbit_pengajar', [
                'jenis' => $this->jenis->label(),
                'jumlah' => $this->jumlah,
            ]);
    }

    public function tautan(object $penerima): ?string
    {
        return match (true) {
            $penerima instanceof Mahasiswa => route('mahasiswa.jadwal-ujian'),
            $penerima instanceof Dosen => route('dosen.jadwal-ujian'),
            default => route('admin.jadwal-ujian.index'),
        };
    }

    public function ajakan(): string
    {
        return __('jadwal_ujian.notifikasi.ajakan');
    }
}

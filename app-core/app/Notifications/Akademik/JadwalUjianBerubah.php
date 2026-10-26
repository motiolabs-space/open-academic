<?php

declare(strict_types=1);

namespace App\Notifications\Akademik;

use App\Enums\KategoriNotifikasi;
use App\Models\Akademik\JadwalUjian;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Dosen;
use App\Notifications\Notifikasi;

/**
 * A published exam session has moved — in time, in room, or in who watches it.
 *
 * Sent to the session's students, its lecturers, and its invigilators before
 * and after the change: someone just taken off a session needs to know they
 * are no longer expected in that room.
 */
class JadwalUjianBerubah extends Notifikasi
{
    public function __construct(public readonly JadwalUjian $jadwal) {}

    public function kategori(): KategoriNotifikasi
    {
        return KategoriNotifikasi::Akademik;
    }

    public function judul(object $penerima): string
    {
        return __('jadwal_ujian.notifikasi.ubah_judul', [
            'jenis' => $this->jadwal->jenis->singkatan(),
            'mk' => $this->jadwal->kelasKuliah->mataKuliah->nama,
        ]);
    }

    public function ringkasan(object $penerima): string
    {
        $ruang = $this->jadwal->ruang->map(fn ($r) => $r->ruang?->kode)->filter()->implode(', ');

        return __('jadwal_ujian.notifikasi.ubah_ringkasan', [
            'kelas' => $this->jadwal->kelasKuliah->namaLengkap(),
            'waktu' => $this->jadwal->rentangWaktu(),
            'ruang' => $ruang !== '' ? $ruang : '—',
        ]);
    }

    public function tautan(object $penerima): ?string
    {
        return match (true) {
            $penerima instanceof Mahasiswa => route('mahasiswa.jadwal-ujian'),
            $penerima instanceof Dosen => route('dosen.jadwal-ujian'),
            default => route('admin.jadwal-ujian.show', $this->jadwal),
        };
    }

    public function tone(): string
    {
        return 'warning';
    }

    public function ajakan(): string
    {
        return __('jadwal_ujian.notifikasi.ajakan');
    }
}

<?php

declare(strict_types=1);

namespace App\Notifications\TugasAkhir;

use App\Enums\KategoriNotifikasi;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\TugasAkhir\Ujian;
use App\Notifications\Notifikasi;
use App\Support\Format;

/**
 * A defence or seminar has been scheduled.
 *
 * Reaches the student and every examiner. An examiner who learns of a panel seat
 * on the morning itself is the reason defences get rescheduled, and the room
 * booking that made it possible is already spent.
 */
class UjianDijadwalkan extends Notifikasi
{
    public function __construct(public readonly Ujian $ujian) {}

    public function kategori(): KategoriNotifikasi
    {
        return KategoriNotifikasi::TugasAkhir;
    }

    public function judul(object $penerima): string
    {
        return $penerima instanceof Mahasiswa
            ? $this->ujian->jenis->label().' Anda telah dijadwalkan'
            : 'Undangan menguji: '.$this->ujian->jenis->label();
    }

    public function ringkasan(object $penerima): string
    {
        $ta = $this->ujian->tugasAkhir;

        $waktu = sprintf(
            '%s, %s–%s%s',
            Format::tanggalPanjang($this->ujian->tanggal),
            substr((string) $this->ujian->jam_mulai, 0, 5),
            substr((string) $this->ujian->jam_selesai, 0, 5),
            $this->ujian->ruang !== null ? ', ruang '.$this->ujian->ruang->kode : '',
        );

        return $penerima instanceof Mahasiswa
            ? "{$this->ujian->jenis->label()} dijadwalkan {$waktu}."
            : sprintf(
                '%s atas nama %s (%s) dijadwalkan %s. Judul: "%s".',
                $this->ujian->jenis->label(),
                $ta->mahasiswa->nama,
                $ta->mahasiswa->nim,
                $waktu,
                $ta->judul,
            );
    }

    public function tautan(object $penerima): ?string
    {
        return $penerima instanceof Mahasiswa
            ? route('mahasiswa.tugas-akhir')
            : route('dosen.tugas-akhir.show', $this->ujian->tugasAkhir);
    }

    public function ajakan(): string
    {
        return 'Lihat Jadwal';
    }
}

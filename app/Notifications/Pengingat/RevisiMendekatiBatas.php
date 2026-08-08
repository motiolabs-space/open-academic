<?php

declare(strict_types=1);

namespace App\Notifications\Pengingat;

use App\Enums\KategoriNotifikasi;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\TugasAkhir\Ujian;
use App\Notifications\Notifikasi;
use App\Support\Format;

/**
 * Post-defence revisions are due soon, or overdue.
 *
 * The failure this exists for: a student passes with corrections, hears
 * "congratulations", and believes they have finished. The manuscript is never
 * accepted, and nobody notices until graduation is refused months later.
 *
 * Goes to the supervisor as well, because the deadline is theirs to enforce and
 * a student chasing a supervisor who does not know is the usual shape of this.
 */
class RevisiMendekatiBatas extends Notifikasi
{
    public function __construct(
        public readonly Ujian $ujian,
        public readonly int $hariTersisa,
    ) {}

    public function kategori(): KategoriNotifikasi
    {
        return KategoriNotifikasi::Pengingat;
    }

    public function judul(object $penerima): string
    {
        return $this->lewat()
            ? 'Batas revisi tugas akhir sudah lewat'
            : 'Batas revisi tugas akhir '.$this->hariTersisa.' hari lagi';
    }

    public function ringkasan(object $penerima): string
    {
        $ta = $this->ujian->tugasAkhir;
        $batas = Format::tanggalPanjang($this->ujian->batas_revisi);

        if ($penerima instanceof Mahasiswa) {
            return "Revisi setelah {$this->ujian->jenis->label()} harus diterima pembimbing "
                ."paling lambat {$batas}. Tugas akhir belum dapat dinyatakan selesai sebelum itu.";
        }

        return sprintf(
            'Revisi %s (%s) berbatas %s dan belum dinyatakan selesai.',
            $ta->mahasiswa->nama,
            $ta->mahasiswa->nim,
            $batas,
        );
    }

    public function tautan(object $penerima): ?string
    {
        return $penerima instanceof Mahasiswa
            ? route('mahasiswa.tugas-akhir')
            : route('dosen.tugas-akhir.show', $this->ujian->tugasAkhir);
    }

    public function tone(): string
    {
        return $this->lewat() ? 'danger' : 'warning';
    }

    public function ajakan(): string
    {
        return 'Buka Tugas Akhir';
    }

    private function lewat(): bool
    {
        return $this->hariTersisa < 0;
    }
}

<?php

declare(strict_types=1);

namespace App\Notifications\TugasAkhir;

use App\Enums\KategoriNotifikasi;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\TugasAkhir\Pembimbing;
use App\Notifications\Notifikasi;

/**
 * A supervisor has been assigned.
 *
 * Goes to both sides, and says something different to each: the student learns
 * who to approach, the lecturer learns they have been given work. The same text
 * for both would leave one of them guessing which it was.
 *
 * This closes the gap the tugas akhir module surfaced but could not fix on its
 * own — a title approved months ago with nobody assigned, where neither party
 * knew an assignment had since happened.
 */
class PembimbingDitetapkan extends Notifikasi
{
    public function __construct(public readonly Pembimbing $pembimbing) {}

    public function kategori(): KategoriNotifikasi
    {
        return KategoriNotifikasi::TugasAkhir;
    }

    public function judul(object $penerima): string
    {
        return $penerima instanceof Mahasiswa
            ? 'Pembimbing tugas akhir ditetapkan'
            : 'Anda ditetapkan sebagai pembimbing';
    }

    public function ringkasan(object $penerima): string
    {
        $ta = $this->pembimbing->tugasAkhir;

        if ($penerima instanceof Mahasiswa) {
            return sprintf(
                '%s ditetapkan sebagai %s Anda. Bimbingan dapat dimulai, dan setiap pertemuan '
                    .'dicatat pada log bimbingan.',
                $this->pembimbing->dosen->namaLengkap(),
                strtolower($this->pembimbing->peran->label()),
            );
        }

        return sprintf(
            'Anda ditetapkan sebagai %s untuk %s (%s), judul "%s".',
            strtolower($this->pembimbing->peran->label()),
            $ta->mahasiswa->nama,
            $ta->mahasiswa->nim,
            $ta->judul,
        );
    }

    public function tautan(object $penerima): ?string
    {
        return $penerima instanceof Mahasiswa
            ? route('mahasiswa.tugas-akhir')
            : route('dosen.tugas-akhir.show', $this->pembimbing->tugasAkhir);
    }

    public function ajakan(): string
    {
        return 'Buka Tugas Akhir';
    }
}

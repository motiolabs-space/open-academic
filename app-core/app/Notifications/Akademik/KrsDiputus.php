<?php

declare(strict_types=1);

namespace App\Notifications\Akademik;

use App\Enums\KategoriNotifikasi;
use App\Enums\KrsStatus;
use App\Models\Akademik\Krs;
use App\Notifications\Notifikasi;

/**
 * A study plan was approved or rejected.
 *
 * Mandatory. This is the message that tells a student whether they are enrolled
 * this semester; a rejection they never see becomes a semester they never
 * attended.
 */
class KrsDiputus extends Notifikasi
{
    public function __construct(
        public readonly Krs $krs,
        public readonly ?string $catatan = null,
    ) {}

    public function kategori(): KategoriNotifikasi
    {
        return KategoriNotifikasi::Akademik;
    }

    public function judul(object $penerima): string
    {
        return $this->disetujui()
            ? 'Rencana studi disetujui'
            : 'Rencana studi dikembalikan';
    }

    public function ringkasan(object $penerima): string
    {
        $term = $this->krs->tahunAkademik->nama;

        if ($this->disetujui()) {
            return "Rencana studi Anda untuk {$term} telah disetujui dosen wali "
                ."({$this->krs->total_sks} SKS).";
        }

        // The reason travels with the message. A rejection without one sends the
        // student to an office to ask, and the answer is already written down.
        return "Rencana studi Anda untuk {$term} dikembalikan dosen wali."
            .($this->catatan !== null ? " Catatan: {$this->catatan}" : '');
    }

    public function tautan(object $penerima): ?string
    {
        return route('mahasiswa.krs');
    }

    public function tone(): string
    {
        return $this->disetujui() ? 'success' : 'warning';
    }

    public function ajakan(): string
    {
        return 'Lihat Rencana Studi';
    }

    private function disetujui(): bool
    {
        return $this->krs->status === KrsStatus::Disetujui;
    }
}

<?php

declare(strict_types=1);

namespace App\Notifications\TugasAkhir;

use App\Enums\KategoriNotifikasi;
use App\Enums\TugasAkhirStatus;
use App\Models\TugasAkhir\TugasAkhir;
use App\Notifications\Notifikasi;

/**
 * A proposed final-project title was approved or rejected.
 *
 * The rejection reason travels with the message. TugasAkhirService already
 * refuses to record a rejection without one, precisely so this notification has
 * something to say beyond "no".
 */
class JudulDiputus extends Notifikasi
{
    public function __construct(public readonly TugasAkhir $tugasAkhir) {}

    public function kategori(): KategoriNotifikasi
    {
        return KategoriNotifikasi::TugasAkhir;
    }

    public function judul(object $penerima): string
    {
        return $this->disetujui() ? 'Judul tugas akhir disetujui' : 'Judul tugas akhir ditolak';
    }

    public function ringkasan(object $penerima): string
    {
        if ($this->disetujui()) {
            return sprintf(
                'Judul "%s" disetujui. Bimbingan dapat dimulai setelah program studi menetapkan pembimbing.',
                $this->tugasAkhir->judul,
            );
        }

        return sprintf(
            'Judul "%s" ditolak. Alasan: %s',
            $this->tugasAkhir->judul,
            $this->tugasAkhir->catatan ?? '—',
        );
    }

    public function tautan(object $penerima): ?string
    {
        return route('mahasiswa.tugas-akhir');
    }

    public function tone(): string
    {
        return $this->disetujui() ? 'success' : 'danger';
    }

    public function ajakan(): string
    {
        return 'Buka Tugas Akhir';
    }

    private function disetujui(): bool
    {
        return $this->tugasAkhir->status !== TugasAkhirStatus::Ditolak;
    }
}

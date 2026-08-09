<?php

declare(strict_types=1);

namespace App\Notifications\Kemahasiswaan;

use App\Enums\HasilEvaluasi;
use App\Enums\KategoriNotifikasi;
use App\Models\Kemahasiswaan\EvaluasiStudi;
use App\Notifications\Notifikasi;

/**
 * An evaluation checkpoint found something.
 *
 * Sent when the finding is recorded, **not** when a decision is made — because
 * the whole value of an early warning is that it arrives while the student can
 * still do something about it. Waiting for the committee means telling them
 * after the term they needed to fix.
 *
 * The message says what the threshold was, never only that they fell short. A
 * student told "your IPK is too low" cannot act; one told "IPK 1,85 against a
 * requirement of 2,00 by the end of semester 4" can.
 */
class PeringatanAkademik extends Notifikasi
{
    public function __construct(public readonly EvaluasiStudi $evaluasi) {}

    public function kategori(): KategoriNotifikasi
    {
        return KategoriNotifikasi::Akademik;
    }

    public function judul(object $penerima): string
    {
        return $this->evaluasi->temuan === HasilEvaluasi::TidakMemenuhi
            ? 'Evaluasi studi: syarat belum terpenuhi'
            : 'Evaluasi studi: perlu perhatian';
    }

    public function ringkasan(object $penerima): string
    {
        $tahap = $this->evaluasi->tahap ?? 'Evaluasi semester';

        return sprintf(
            '%s pada %s: %s. Hubungi dosen wali Anda untuk membicarakan rencana perbaikan.',
            $tahap,
            $this->evaluasi->tahunAkademik->nama,
            $this->evaluasi->alasan(),
        );
    }

    public function tautan(object $penerima): ?string
    {
        return route('mahasiswa.khs');
    }

    public function tone(): string
    {
        return $this->evaluasi->temuan->tone();
    }

    public function ajakan(): string
    {
        return 'Lihat Hasil Studi';
    }
}

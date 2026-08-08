<?php

declare(strict_types=1);

namespace App\Notifications\Sdm;

use App\Enums\KategoriNotifikasi;
use App\Enums\StatusBkd;
use App\Models\Sdm\BkdLaporan;
use App\Notifications\Notifikasi;

/**
 * The assessors have ruled on a workload report, or sent it back.
 *
 * Mandatory, because a returned report has a deadline attached and the
 * allowance depends on refiling before it. Somebody who muted this and missed
 * the return would lose a semester's payment over a preference set once.
 */
class BkdDinilai extends Notifikasi
{
    public function __construct(public readonly BkdLaporan $laporan) {}

    public function kategori(): KategoriNotifikasi
    {
        return KategoriNotifikasi::Kepegawaian;
    }

    public function judul(object $penerima): string
    {
        return $this->dikembalikan()
            ? 'Laporan BKD dikembalikan'
            : 'Hasil penilaian BKD';
    }

    public function ringkasan(object $penerima): string
    {
        $term = $this->laporan->tahunAkademik->nama;

        // The reason travels with the message. Without it the recipient has to
        // open a screen to find out whether anything is required of them.
        if ($this->dikembalikan()) {
            return "Laporan BKD Anda untuk {$term} dikembalikan asesor untuk diperbaiki."
                .($this->laporan->catatan_asesor !== null
                    ? " Catatan: {$this->laporan->catatan_asesor}"
                    : '');
        }

        return sprintf(
            'Laporan BKD Anda untuk %s dinilai: %s (%s SKS).%s',
            $term,
            $this->laporan->kesimpulan?->label() ?? '—',
            number_format($this->laporan->sksTotal(), 2, ',', '.'),
            $this->laporan->catatan_asesor !== null
                ? " Catatan: {$this->laporan->catatan_asesor}"
                : '',
        );
    }

    public function tautan(object $penerima): ?string
    {
        return route('dosen.bkd');
    }

    public function tone(): string
    {
        return $this->dikembalikan() ? 'warning' : ($this->laporan->kesimpulan?->tone() ?? 'info');
    }

    public function ajakan(): string
    {
        return $this->dikembalikan() ? 'Perbaiki Laporan' : 'Lihat Laporan BKD';
    }

    private function dikembalikan(): bool
    {
        return $this->laporan->status === StatusBkd::Dikembalikan;
    }
}

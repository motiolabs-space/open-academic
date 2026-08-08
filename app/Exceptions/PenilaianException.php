<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * A grading rule refused the operation. Shares the academic-rule rendering
 * path, so the lecturer reads the reason on the page they were already on.
 */
class PenilaianException extends AturanAkademikException
{
    public static function periodeTertutup(): self
    {
        return new self('Periode pengisian nilai untuk semester ini belum dibuka atau sudah ditutup.');
    }

    public static function bobotTidakSeratus(int $total): self
    {
        return new self("Total bobot komponen penilaian harus 100%, saat ini {$total}%.");
    }

    public static function komponenKosong(): self
    {
        return new self('Tetapkan minimal satu komponen penilaian sebelum mengisi nilai.');
    }

    public static function nilaiDiLuarRentang(): self
    {
        return new self('Nilai komponen harus berada di rentang 0 sampai 100.');
    }

    public static function kelasSudahFinal(): self
    {
        return new self('Nilai kelas ini sudah difinalisasi dan tidak dapat diubah. Gunakan jalur koreksi ter-audit.');
    }

    public static function adaNilaiKosong(int $jumlah): self
    {
        return new self("Masih ada {$jumlah} isian nilai yang kosong. Lengkapi seluruh komponen sebelum finalisasi.");
    }

    public static function tanpaPeserta(): self
    {
        return new self('Belum ada mahasiswa yang mengambil kelas ini.');
    }

    public static function alasanKoreksiWajib(): self
    {
        return new self('Koreksi nilai final wajib disertai alasan yang tercatat pada jejak audit.');
    }
}

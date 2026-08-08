<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * A business rule refused an academic operation.
 *
 * Messages are in Bahasa Indonesia because they are shown verbatim to the
 * person who tripped the rule — a student who cannot add a course deserves to
 * read why, not a stack trace. The named constructors keep that wording in one
 * place instead of scattered across services and controllers.
 */
class AturanAkademikException extends RuntimeException
{
    public static function semesterTidakAktif(): self
    {
        return new self('Belum ada semester yang ditetapkan aktif. Hubungi bagian akademik.');
    }

    public static function mahasiswaTidakAktif(string $status): self
    {
        return new self("Status Anda saat ini {$status}. Hanya mahasiswa berstatus Aktif yang dapat mengisi rencana studi.");
    }

    public static function krsBelumDibuka(): self
    {
        return new self('Pengisian KRS belum dibuka atau sudah ditutup sesuai kalender akademik.');
    }

    public static function krsTerkunciPembayaran(int $persenMinimum): self
    {
        return new self("Pengisian KRS terkunci hingga pembayaran mencapai {$persenMinimum}% dari total tagihan semester ini.");
    }

    public static function krsTidakDapatDiubah(string $status): self
    {
        return new self("Rencana studi berstatus {$status} tidak dapat diubah lagi.");
    }

    public static function kelasBukanSemesterIni(): self
    {
        return new self('Kelas tersebut tidak ditawarkan pada semester aktif.');
    }

    public static function kelasDiLuarKurikulum(string $mataKuliah): self
    {
        return new self("Mata kuliah {$mataKuliah} tidak terdaftar pada kurikulum yang Anda ikuti.");
    }

    public static function kelasSudahDiambil(string $mataKuliah): self
    {
        return new self("{$mataKuliah} sudah ada di rencana studi Anda.");
    }

    public static function mataKuliahSudahLulus(string $mataKuliah): self
    {
        return new self("{$mataKuliah} sudah Anda lulusi dan tidak perlu diambil ulang.");
    }

    public static function kuotaHabis(string $kelas): self
    {
        return new self("Kuota {$kelas} sudah penuh.");
    }

    /** @param array<int, string> $belumLulus */
    public static function prasyaratBelumTerpenuhi(string $mataKuliah, array $belumLulus): self
    {
        return new self(sprintf(
            'Prasyarat %s belum terpenuhi: %s.',
            $mataKuliah,
            implode(', ', $belumLulus),
        ));
    }

    public static function melebihiBatasSks(int $diambil, int $batas): self
    {
        return new self("Total {$diambil} SKS melebihi batas {$batas} SKS yang ditetapkan dari IPS semester lalu.");
    }

    public static function jadwalBentrok(string $mataKuliah, string $waktu): self
    {
        return new self("Jadwal bentrok dengan {$mataKuliah} pada {$waktu}.");
    }

    public static function krsKosong(): self
    {
        return new self('Rencana studi masih kosong. Pilih minimal satu kelas sebelum mengajukan.');
    }

    public static function transisiTidakSah(string $dari, string $ke): self
    {
        return new self("Rencana studi berstatus {$dari} tidak dapat diubah menjadi {$ke}.");
    }

    public static function bukanDosenWali(): self
    {
        return new self('Hanya Dosen Wali mahasiswa yang bersangkutan yang dapat menyetujui rencana studi ini.');
    }

    public static function catatanPenolakanWajib(): self
    {
        return new self('Penolakan rencana studi wajib disertai catatan untuk mahasiswa.');
    }
}

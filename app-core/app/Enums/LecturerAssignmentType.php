<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * External or non-teaching assignment held by a lecturer.
 *
 * Feeds two indicators that Open Campus computes from Bridge data:
 *  - IKU 3: lecturers active outside the campus
 *  - IKU 4: practitioners teaching inside the campus (PraktisiMengajar)
 */
enum LecturerAssignmentType: string
{
    case PraktisiMengajar = 'praktisi_mengajar';
    case TugasIndustri = 'tugas_industri';
    case Penelitian = 'penelitian';
    case Pengabdian = 'pengabdian';
    case StudiLanjut = 'studi_lanjut';
    case Sertifikasi = 'sertifikasi_kompetensi';
    case PembinaMahasiswa = 'pembina_prestasi_mahasiswa';

    public function label(): string
    {
        return match ($this) {
            self::PraktisiMengajar => 'Praktisi Mengajar di Kampus',
            self::TugasIndustri => 'Bekerja / Tugas di Industri',
            self::Penelitian => 'Penelitian Kolaboratif',
            self::Pengabdian => 'Pengabdian kepada Masyarakat',
            self::StudiLanjut => 'Studi Lanjut',
            self::Sertifikasi => 'Sertifikasi Kompetensi',
            self::PembinaMahasiswa => 'Membina Mahasiswa Berprestasi',
        };
    }

    /** IKU 3 counts lecturers whose activity takes place outside the campus. */
    public function countsForIku3(): bool
    {
        return in_array($this, [
            self::TugasIndustri,
            self::Penelitian,
            self::Pengabdian,
            self::StudiLanjut,
            self::Sertifikasi,
            self::PembinaMahasiswa,
        ], true);
    }

    /** IKU 4 counts practitioners brought in to teach. */
    public function countsForIku4(): bool
    {
        return $this === self::PraktisiMengajar;
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            fn (array $carry, self $case): array => $carry + [$case->value => $case->label()],
            [],
        );
    }
}

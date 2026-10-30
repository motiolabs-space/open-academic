<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * External or non-teaching assignment held by a lecturer.
 *
 * Fed two indicators of the older eight-indicator IKU numbering, which the
 * legacy iku-data keys and the `iku` field on Bridge assignments still report:
 *  - IKU 3: lecturers active outside the campus
 *  - IKU 4: practitioners teaching inside the campus (PraktisiMengajar)
 *
 * Neither has a counterpart in Kepmendiktisaintek 358/M/KEP/2025; the outputs
 * recorded on an assignment feed its IKU 4-6 instead (ADR-036), and policy
 * drafting is its IKU 8 (ADR-041 §1). The legacy counts are frozen, so the
 * newer type feeds neither IKU 3 nor IKU 4 of the old numbering.
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
    case PenyusunanKebijakan = 'penyusunan_kebijakan';

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
            self::PenyusunanKebijakan => __('portofolio.kebijakan.jenis'),
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

    /**
     * IKU 8 (2026 numbering): taking part in drafting a public or sector
     * policy. The only type whose row needs the kebijakan_* columns and an
     * official document before it may be saved.
     */
    public function untukIku8(): bool
    {
        return $this === self::PenyusunanKebijakan;
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

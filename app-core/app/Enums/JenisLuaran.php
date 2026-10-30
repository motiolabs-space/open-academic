<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What an activity produced.
 *
 * The list ministry forms ask about, kept deliberately short. Anything finer is
 * a property of the specific output: whether a patent is granted or pending
 * belongs in `luaran_identitas` where a human can read it. The index and
 * quartile of a publication are the exception — IKU 6 counts by them — and
 * have their own columns, declared and verified rather than looked up
 * (ADR-041 §2).
 */
enum JenisLuaran: string
{
    case Jurnal = 'jurnal';
    case Prosiding = 'prosiding';
    case Buku = 'buku';
    case HakCipta = 'hak_cipta';
    case Paten = 'paten';
    case Produk = 'produk';
    case Laporan = 'laporan';

    public function label(): string
    {
        return match ($this) {
            self::Jurnal => 'Artikel Jurnal',
            self::Prosiding => 'Prosiding Konferensi',
            self::Buku => 'Buku / Bab Buku',
            self::HakCipta => 'Hak Cipta',
            self::Paten => 'Paten',
            self::Produk => 'Produk / Purwarupa',
            self::Laporan => 'Laporan',
        };
    }

    /** Journal articles and proceedings: the outputs IKU 6 reads, with index and DOI. */
    public function publikasi(): bool
    {
        return $this === self::Jurnal || $this === self::Prosiding;
    }

    /** What the identifier field is called for this kind of output. */
    public function labelIdentitas(): string
    {
        return match ($this) {
            self::Jurnal => 'DOI / ISSN',
            self::Prosiding => 'DOI / ISBN',
            self::Buku => 'ISBN',
            self::HakCipta, self::Paten => 'Nomor pendaftaran',
            self::Produk, self::Laporan => 'Tautan / nomor dokumen',
        };
    }

    /**
     * Outputs the older eight-indicator IKU 5 counted as used by society or
     * recognised internationally. Kept for the legacy `berpotensi_iku5` count;
     * the 2026 numbering reads outputs per indicator (DataIku2026).
     *
     * Reported as a bucket, not applied as a threshold — the qualifying set is
     * set by regulation and changes, so Open Campus decides. Same stance as
     * IkuDataController.
     */
    public function luaranIku5(): bool
    {
        return in_array($this, [
            self::Jurnal,
            self::Buku,
            self::HakCipta,
            self::Paten,
            self::Produk,
        ], true);
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

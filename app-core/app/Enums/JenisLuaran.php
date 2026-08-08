<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What an activity produced.
 *
 * The list ministry forms ask about, kept deliberately short. Anything finer —
 * which quartile a journal sits in, whether a patent is granted or pending — is
 * a property of the specific output and belongs in `luaran_identitas` where a
 * human can read it, not in an enum this codebase would have to keep in step
 * with an external ranking.
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
     * Outputs IKU 5 counts as used by society or recognised internationally.
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

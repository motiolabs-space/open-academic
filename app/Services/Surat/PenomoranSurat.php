<?php

declare(strict_types=1);

namespace App\Services\Surat;

use App\Enums\JenisSurat;
use App\Models\Surat\Surat;
use App\Services\Branding\BrandingService;
use Illuminate\Database\UniqueConstraintViolationException;
use RuntimeException;

/**
 * Assigning a letter its number.
 *
 * The number is the campus's claim that this document came from them. Two
 * letters sharing one is a document nobody can stand behind; a gap in the
 * sequence is a question somebody has to answer during an audit, usually years
 * later and without anyone who remembers.
 *
 * Both failures come from the same place — reading the current maximum and then
 * writing, with a gap in between where a second request fits. The composite
 * unique index on (jenis, tahun, nomor_urut) closes that gap, and this class
 * retries against it rather than trying to be clever about locking.
 *
 * Retrying rather than locking, on purpose: a `SELECT … FOR UPDATE` over the
 * whole year's letters serialises every issue on a busy afternoon, and the
 * contention it prevents is rare enough that losing a round trip to it is
 * cheaper than holding a lock nobody usually needs.
 */
class PenomoranSurat
{
    /** Enough attempts to outlast realistic contention; a wall, not a limit. */
    private const PERCOBAAN = 25;

    private const BULAN_ROMAWI = [
        1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI',
        7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII',
    ];

    public function __construct(private readonly BrandingService $brand) {}

    /**
     * Numbers a letter and saves it.
     *
     * The write is what claims the number; nothing is reserved beforehand. A
     * request that is later rejected therefore consumes nothing, which is why
     * nomor_urut stays null until this runs.
     */
    public function bubuhkan(Surat $surat): Surat
    {
        $tahun = (int) now()->year;

        for ($percobaan = 0; $percobaan < self::PERCOBAAN; $percobaan++) {
            $urut = $this->berikutnya($surat->jenis, $tahun);

            try {
                $surat->forceFill([
                    'tahun' => $tahun,
                    'nomor_urut' => $urut,
                    'nomor' => $this->format($surat->jenis, $urut, $tahun),
                ])->save();

                return $surat;
            } catch (UniqueConstraintViolationException) {
                // Someone took this number between the read and the write.
                // Read again and try the next one.
                continue;
            }
        }

        throw new RuntimeException(
            'Tidak berhasil memperoleh nomor surat setelah '.self::PERCOBAAN.' percobaan. '
                .'Periksa apakah ada proses lain yang menerbitkan surat secara massal.',
        );
    }

    /**
     * The next sequence number for this type this year.
     *
     * Per type and per year, so an SKPI and a certificate of enrolment do not
     * share a run and every January starts at one — the convention nearly every
     * campus already uses on paper.
     */
    private function berikutnya(JenisSurat $jenis, int $tahun): int
    {
        return 1 + (int) Surat::query()
            ->withTrashed()
            ->where('jenis', $jenis->value)
            ->where('tahun', $tahun)
            ->max('nomor_urut');
    }

    /**
     * Renders the configured pattern.
     *
     * Soft-deleted rows are counted above deliberately: a number that was once
     * printed on a document has left the building, and handing it to a second
     * letter later would mean two genuine papers claiming the same identity.
     */
    private function format(JenisSurat $jenis, int $urut, int $tahun): string
    {
        return str_replace(
            ['{urut}', '{kode}', '{bulan}', '{tahun}', '{institusi}'],
            [
                str_pad((string) $urut, (int) config('surat.panjang_urut'), '0', STR_PAD_LEFT),
                $jenis->kode(),
                self::BULAN_ROMAWI[(int) now()->month],
                (string) $tahun,
                $this->brand->institutionCode(),
            ],
            (string) config('surat.pola_nomor'),
        );
    }
}

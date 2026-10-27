<?php

declare(strict_types=1);

namespace App\DTOs\Sdm;

use App\Enums\JabatanFungsional;
use App\Enums\UnsurAngkaKredit;
use App\Models\Sdm\Dosen;
use Carbon\CarbonInterface;

/**
 * One lecturer's credit, summed: what the ledger supports, not a verdict.
 *
 * Every figure is in hundredths. "Kum" figures count only what was completed
 * after the TMT of the rank in force — what came before is already inside
 * that rank's cumulative credit. `kumRatus` is what was done; `diakuiRatus`
 * is what can be counted once Kum D and E are held to their share.
 *
 * `siap` means the configured thresholds are met by these numbers. Whether
 * the promotion is granted is decided by the ministry's assessors.
 */
final readonly class RekapAngkaKredit
{
    /**
     * @param array<string, int> $kumRatus keyed by UnsurAngkaKredit value
     * @param array<string, int> $diakuiRatus keyed by UnsurAngkaKredit value
     */
    public function __construct(
        public Dosen $dosen,
        public ?JabatanFungsional $jabatan,
        public ?CarbonInterface $tmtJabatan,
        public int $akTerakhirRatus,
        public array $kumRatus,
        public array $diakuiRatus,
        public int $totalDupakRatus,
        public int $akKumulatifRatus,
        public ?JabatanFungsional $berikutnya,
        public ?int $syaratRatus,
        public ?int $selisihRatus,
        public int $penelitianMinimumRatus,
        public bool $memenuhiPenelitian,
        public bool $siap,
        public bool $adaPerkiraan,
    ) {}

    public function kum(UnsurAngkaKredit $unsur): int
    {
        return $this->kumRatus[$unsur->value] ?? 0;
    }

    public function diakui(UnsurAngkaKredit $unsur): int
    {
        return $this->diakuiRatus[$unsur->value] ?? 0;
    }

    /** Whether the cap on D or E removed anything. */
    public function dipangkas(UnsurAngkaKredit $unsur): bool
    {
        return $this->diakui($unsur) < $this->kum($unsur);
    }
}

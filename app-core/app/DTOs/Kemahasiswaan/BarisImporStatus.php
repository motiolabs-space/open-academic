<?php

declare(strict_types=1);

namespace App\DTOs\Kemahasiswaan;

use App\Enums\StatusPekerjaanAlumni;
use Carbon\CarbonImmutable;

/**
 * One line of an employment-status file, read and checked.
 *
 * `baris` is the line number in the file as a spreadsheet shows it (the
 * header is line 1), so a rejected row can be found by whoever fixes it.
 */
final readonly class BarisImporStatus
{
    /** @param array<int, string> $alasan why the row is refused; empty when accepted */
    public function __construct(
        public int $baris,
        public string $nim,
        public ?int $alumniId = null,
        public ?StatusPekerjaanAlumni $status = null,
        public ?string $instansi = null,
        public ?string $jabatan = null,
        public ?CarbonImmutable $mulaiBekerja = null,
        public ?int $masaTungguBulan = null,
        public ?CarbonImmutable $tanggalSurvei = null,
        public array $alasan = [],
        public bool $tidakBerubah = false,
    ) {}

    public function diterima(): bool
    {
        return $this->alasan === [];
    }
}

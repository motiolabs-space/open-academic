<?php

declare(strict_types=1);

namespace App\DTOs\Kemahasiswaan;

use App\Enums\SumberStatusPekerjaan;

/**
 * The outcome of checking — or applying — an employment-status file.
 *
 * `galatBerkas` are problems with the file as a whole (no `nim` column, no
 * rows); when there are any, nothing in it is applied.
 */
final readonly class HasilImporStatus
{
    /**
     * @param array<int, BarisImporStatus> $baris
     * @param array<int, string> $galatBerkas
     */
    public function __construct(
        public SumberStatusPekerjaan $sumber,
        public array $baris,
        public array $galatBerkas = [],
        public int $diterapkan = 0,
    ) {}

    /** @return array<int, BarisImporStatus> accepted rows that change something */
    public function akanDiterapkan(): array
    {
        return array_values(array_filter($this->baris, fn (BarisImporStatus $b): bool => $b->diterima() && !$b->tidakBerubah));
    }

    /** @return array<int, BarisImporStatus> */
    public function tidakBerubah(): array
    {
        return array_values(array_filter($this->baris, fn (BarisImporStatus $b): bool => $b->diterima() && $b->tidakBerubah));
    }

    /** @return array<int, BarisImporStatus> */
    public function ditolak(): array
    {
        return array_values(array_filter($this->baris, fn (BarisImporStatus $b): bool => !$b->diterima()));
    }

    public function dapatDiterapkan(): bool
    {
        return $this->galatBerkas === [] && $this->akanDiterapkan() !== [];
    }
}

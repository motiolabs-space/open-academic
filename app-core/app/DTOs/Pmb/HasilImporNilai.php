<?php

declare(strict_types=1);

namespace App\DTOs\Pmb;

/**
 * The outcome of one score import.
 *
 * Either `galat` is empty and `tersimpan` rows were written, or `galat` lists
 * every problem in the file and nothing was written. Never both.
 */
final readonly class HasilImporNilai
{
    /** @param array<int, string> $galat */
    public function __construct(
        public int $tersimpan,
        public array $galat = [],
        public int $lulusOtomatis = 0,
        public int $tidakLulusOtomatis = 0,
    ) {}

    public function berhasil(): bool
    {
        return $this->galat === [];
    }
}

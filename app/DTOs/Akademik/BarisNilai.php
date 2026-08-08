<?php

declare(strict_types=1);

namespace App\DTOs\Akademik;

use App\Enums\GradeLetter;

/**
 * One student's row on the grade-entry sheet: the component scores as entered,
 * plus what they add up to.
 */
final readonly class BarisNilai
{
    /**
     * @param array<int, float|null> $komponen komponen_nilai id => score
     */
    public function __construct(
        public int $krsDetailId,
        public string $nim,
        public string $nama,
        public array $komponen,
        public ?float $nilaiAkhir,
        public ?GradeLetter $huruf,
        public bool $final,
        public bool $lengkap,
        public ?float $persenKehadiran,
        public bool $layakUas,
    ) {}

    public function bermasalah(): bool
    {
        return !$this->layakUas || !$this->lengkap;
    }
}

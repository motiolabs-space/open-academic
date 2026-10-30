<?php

declare(strict_types=1);

namespace App\DTOs\Akademik;

/** One CPMK in a submitted set. */
final readonly class CpmkData
{
    /**
     * @param array<int, int> $cpl prodi_cpl id => weight per hundred
     * @param list<SubCpmkData> $sub
     */
    public function __construct(
        public string $kode,
        public string $rumusan,
        public array $cpl,
        public array $sub,
    ) {}
}

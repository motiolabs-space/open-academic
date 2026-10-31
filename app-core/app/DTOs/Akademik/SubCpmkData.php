<?php

declare(strict_types=1);

namespace App\DTOs\Akademik;

/** One sub-CPMK in a submitted set; `bobot` is its share of the parent, per hundred. */
final readonly class SubCpmkData
{
    public function __construct(
        public string $kode,
        public string $rumusan,
        public int $bobot,
    ) {}
}

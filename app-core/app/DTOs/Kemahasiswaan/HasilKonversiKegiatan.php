<?php

declare(strict_types=1);

namespace App\DTOs\Kemahasiswaan;

/**
 * What one conversion run did: how many participants now carry the course
 * grade, and why the rest did not.
 */
final readonly class HasilKonversiKegiatan
{
    /** @param array<string, string> $dilewati NIM => reason */
    public function __construct(
        public int $dikonversi,
        public array $dilewati = [],
    ) {}
}

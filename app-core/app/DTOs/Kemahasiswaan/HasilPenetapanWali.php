<?php

declare(strict_types=1);

namespace App\DTOs\Kemahasiswaan;

/**
 * What one assignment run changed.
 *
 * `tetap` counts students who already had the advisor asked for — re-running
 * an even split over a cohort that is already even changes nothing.
 */
final readonly class HasilPenetapanWali
{
    public function __construct(
        public int $diubah,
        public int $tetap,
        public int $krsDialihkan,
    ) {}
}

<?php

declare(strict_types=1);

namespace App\DTOs\Feeder;

use App\Enums\JenisTemuanKamus;

/**
 * One disagreement between a mapper and the dictionary.
 *
 * `kita` is the name the mapper sends, `feeder` the name the dictionary
 * lists; a rename carries both.
 */
final readonly class TemuanKamus
{
    public function __construct(
        public JenisTemuanKamus $jenis,
        public ?string $kita = null,
        public ?string $feeder = null,
        public bool $wajib = false,
        public ?string $keterangan = null,
    ) {}
}

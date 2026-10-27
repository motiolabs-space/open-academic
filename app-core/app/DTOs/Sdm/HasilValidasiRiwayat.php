<?php

declare(strict_types=1);

namespace App\DTOs\Sdm;

use Illuminate\Database\Eloquent\Model;

/**
 * An approved row, and anything its approval set in motion that the staff
 * member should hear about — a retirement whose account could not be closed
 * yet, for instance. Sentences, so the screen shows them as they are.
 */
final readonly class HasilValidasiRiwayat
{
    /** @param array<int, string> $catatan */
    public function __construct(
        public Model $baris,
        public array $catatan = [],
    ) {}
}

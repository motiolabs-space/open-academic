<?php

declare(strict_types=1);

namespace App\DTOs\Kemahasiswaan;

use App\Enums\KategoriSimkatmawa;
use App\Models\Kemahasiswaan\PoinMahasiswa;

/**
 * One row of a SIMKATMAWA table, with what stops it from being reported.
 *
 * `galat` are omissions the ministry would refuse the row for; the row is
 * left out of the download until they are fixed. `peringatan` are omissions
 * it accepts at a lower score — reported, but shown, so the decision to send
 * a weaker row is a person's and not the export's.
 */
final readonly class BarisSimkatmawa
{
    /**
     * @param array<string, string|int|null> $nilai column key => cell
     * @param array<int, string> $galat
     * @param array<int, string> $peringatan
     */
    public function __construct(
        public KategoriSimkatmawa $kategori,
        public PoinMahasiswa $poin,
        public array $nilai,
        public array $galat = [],
        public array $peringatan = [],
    ) {}

    public function siap(): bool
    {
        return $this->galat === [];
    }
}

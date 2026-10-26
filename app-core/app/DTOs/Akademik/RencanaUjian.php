<?php

declare(strict_types=1);

namespace App\DTOs\Akademik;

use App\Models\Akademik\KelasKuliah;
use Illuminate\Support\Collection;

/**
 * What the bulk scheduler would do: the sessions it placed, and the classes it
 * could not place with the reason why.
 *
 * The second list is the reason this is a preview at all. A scheduler that
 * silently drops the classes it could not fit leaves the registrar to discover
 * them on exam day.
 */
final readonly class RencanaUjian
{
    /**
     * @param Collection<int, UsulanJadwalUjian> $usulan
     * @param Collection<int, array{kelas: KelasKuliah, alasan: string}> $tidakTerjadwal
     */
    public function __construct(
        public Collection $usulan,
        public Collection $tidakTerjadwal,
    ) {}
}

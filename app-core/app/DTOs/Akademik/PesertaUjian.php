<?php

declare(strict_types=1);

namespace App\DTOs\Akademik;

use App\Models\Akademik\Ruang;
use App\Models\Kemahasiswaan\Mahasiswa;

/**
 * One student on an exam session's list: where they sit and whether they may.
 *
 * `alasan` is empty exactly when `layak` is true. Facts that are shown but do
 * not decide anything under the campus's policy — attendance on a mid-term, a
 * balance when arrears are not held against cards — stay in their own fields.
 */
final readonly class PesertaUjian
{
    /** @param list<string> $alasan */
    public function __construct(
        public Mahasiswa $mahasiswa,
        public ?Ruang $ruang,
        public ?float $persenKehadiran,
        public bool $memenuhiKehadiran,
        public int $tunggakan,
        public bool $layak,
        public array $alasan,
    ) {}
}

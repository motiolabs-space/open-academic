<?php

declare(strict_types=1);

namespace App\DTOs\Akademik;

use App\Models\Akademik\Ruang;
use Illuminate\Support\Collection;

/**
 * The answer to a free-room question. Read-only: nothing is reserved.
 */
final readonly class HasilRuangKosong
{
    /**
     * @param Collection<int, Ruang> $kosong smallest room that fits first
     * @param Collection<int, array{ruang: Ruang, oleh: list<string>}> $terpakai rooms that fit but are taken, and by what
     */
    public function __construct(
        public KriteriaRuangKosong $kriteria,
        public Collection $kosong,
        public Collection $terpakai,

        /** The campus-wide holiday declared for the date; lectures do not run on it. */
        public ?string $libur,

        /** Whether the window falls outside the campus's opening hours or days. */
        public bool $diLuarOperasional,
    ) {}
}

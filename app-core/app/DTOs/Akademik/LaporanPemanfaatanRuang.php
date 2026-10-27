<?php

declare(strict_types=1);

namespace App\DTOs\Akademik;

use Illuminate\Support\Collection;

/**
 * A term's room utilisation: every room, what overfilled them, and the frame
 * the percentages were measured against.
 */
final readonly class LaporanPemanfaatanRuang
{
    /**
     * @param Collection<int, PemanfaatanRuang> $ruang in the order the filter asked for
     * @param Collection<int, KelebihanPeserta> $kelebihan largest excess first
     * @param list<int> $hariOperasional ISO weekdays
     * @param list<int> $kolomPeta heat-map column starts, minutes after midnight
     */
    public function __construct(
        public FilterPemanfaatanRuang $filter,
        public Collection $ruang,
        public Collection $kelebihan,
        public array $hariOperasional,
        public array $kolomPeta,
        public int $langkahPeta,
        public string $jamMulai,
        public string $jamSelesai,
        public float $jumlahMinggu,

        /** Operating days in the term lost to a campus-wide holiday. */
        public int $hariLibur,

        public int $ambangJarang,
    ) {}

    /** @return Collection<int, PemanfaatanRuang> */
    public function jarang(): Collection
    {
        return $this->ruang->filter(fn (PemanfaatanRuang $r): bool => $r->jarang)->values();
    }

    /** Occupied over available hours across every listed room. */
    public function rerataPemanfaatan(): ?float
    {
        $tersedia = $this->ruang->sum(fn (PemanfaatanRuang $r): float => $r->jamTersediaPerMinggu);

        return $tersedia > 0
            ? $this->ruang->sum(fn (PemanfaatanRuang $r): float => $r->jamTerpakaiPerMinggu) / $tersedia * 100
            : null;
    }

    public function cariRuang(?string $uuid): ?PemanfaatanRuang
    {
        return $uuid === null ? null : $this->ruang->first(fn (PemanfaatanRuang $r): bool => $r->uuid === $uuid);
    }

    /**
     * The heat map of every listed room, averaged cell by cell.
     *
     * @return array<int, array<int, ?float>>
     */
    public function petaPanasRerata(): array
    {
        $peta = [];

        foreach ($this->hariOperasional as $hari) {
            foreach ($this->kolomPeta as $kolom) {
                $nilai = $this->ruang
                    ->map(fn (PemanfaatanRuang $r): ?float => $r->petaPanas[$hari][$kolom] ?? null)
                    ->filter(fn (?float $v): bool => $v !== null);

                $peta[$hari][$kolom] = $nilai->isEmpty() ? null : (float) $nilai->avg();
            }
        }

        return $peta;
    }
}

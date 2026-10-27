<?php

declare(strict_types=1);

namespace App\DTOs\Akademik;

/**
 * How much one room was used in a term.
 *
 * Hours are weekly averages over the term — the totals divided by the number
 * of weeks — so a room's figures read the way a timetable is planned, and an
 * exam fortnight or a holiday shows up as a change in the average rather than
 * as a separate number nobody compares.
 */
final readonly class PemanfaatanRuang
{
    /**
     * @param array<int, array<int, ?float>> $petaPanas ISO weekday => [column start in minutes => share occupied 0..1, null when that weekday had no available day]
     */
    public function __construct(
        public int $ruangId,
        public string $uuid,
        public string $kode,
        public string $nama,
        public string $jenis,
        public int $kapasitas,
        public bool $aktif,
        public ?string $gedungKode,
        public ?string $gedungNama,

        /** Opening hours on non-holiday operating days. */
        public float $jamTersediaPerMinggu,

        /** Occupied hours inside those opening hours; overlaps counted once. */
        public float $jamTerpakaiPerMinggu,

        /** Occupied hours outside them: evenings, closed weekdays, holidays. */
        public float $jamDiLuarPerMinggu,

        /** Seats taken over seats offered, weighted by hours; null when capacity is unknown or the room was unused. */
        public ?float $okupansiPersen,

        public int $slotKuliah,
        public int $sesiUjian,
        public int $kelebihan,
        public bool $jarang,
        public array $petaPanas,
    ) {}

    /** Share of available hours occupied; null when the room had none available. */
    public function pemanfaatanPersen(): ?float
    {
        return $this->jamTersediaPerMinggu > 0
            ? $this->jamTerpakaiPerMinggu / $this->jamTersediaPerMinggu * 100
            : null;
    }

    /** Available hours nothing was scheduled in. */
    public function jamKosongPerMinggu(): float
    {
        return max(0.0, $this->jamTersediaPerMinggu - $this->jamTerpakaiPerMinggu);
    }
}

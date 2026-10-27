<?php

declare(strict_types=1);

namespace App\DTOs\Akademik;

use App\Models\Akademik\TahunAkademik;
use Illuminate\Support\Carbon;

/**
 * A free-room question: which rooms are empty at this time, seating this many.
 *
 * Asked either of a weekday — when laying out the weekly class timetable — or
 * of a date, when placing an exam or a make-up session. A date also checks the
 * exams already published on it and the holidays declared for it.
 */
final readonly class KriteriaRuangKosong
{
    public function __construct(
        public TahunAkademik $term,

        /** ISO weekday; derived from the date when one is given. */
        public int $hari,

        public string $jamMulai,
        public string $jamSelesai,
        public ?Carbon $tanggal = null,
        public int $kapasitasMinimum = 0,
        public ?int $gedungId = null,
        public ?string $jenis = null,
    ) {}

    /**
     * @param array{hari?: int|string|null, tanggal?: ?string, jam_mulai: string, jam_selesai: string, kapasitas_min?: int|string|null, gedung_id?: int|string|null, jenis?: ?string} $data
     */
    public static function fromArray(TahunAkademik $term, array $data): self
    {
        $tanggal = filled($data['tanggal'] ?? null) ? Carbon::parse($data['tanggal'])->startOfDay() : null;

        return new self(
            term: $term,
            hari: $tanggal?->dayOfWeekIso ?? (int) ($data['hari'] ?? 1),
            jamMulai: substr($data['jam_mulai'], 0, 5),
            jamSelesai: substr($data['jam_selesai'], 0, 5),
            tanggal: $tanggal,
            kapasitasMinimum: (int) ($data['kapasitas_min'] ?? 0),
            gedungId: filled($data['gedung_id'] ?? null) ? (int) $data['gedung_id'] : null,
            jenis: filled($data['jenis'] ?? null) ? (string) $data['jenis'] : null,
        );
    }
}

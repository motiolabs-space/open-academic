<?php

declare(strict_types=1);

namespace App\DTOs\Akademik;

/**
 * One use of a room by more people than it seats.
 *
 * A lecture slot whose approved enrolment exceeds the room's capacity, or an
 * exam room given more students than the room holds. Reported, never refused:
 * the timetable already warns about it (BentrokJadwal::kapasitas), and whether
 * extra chairs are fine is the registrar's call.
 */
final readonly class KelebihanPeserta
{
    public function __construct(
        public int $ruangId,
        public string $ruangKode,
        public string $kelas,

        /** "kuliah", or the exam type's value ("uts", "uas"). */
        public string $jenis,

        public string $waktu,
        public int $peserta,
        public int $kapasitas,
    ) {}

    public function selisih(): int
    {
        return $this->peserta - $this->kapasitas;
    }
}

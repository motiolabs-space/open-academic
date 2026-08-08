<?php

declare(strict_types=1);

namespace App\DTOs\Akademik;

/**
 * One reason a proposed timetable slot does not work.
 *
 * `menghalangi` separates the two kinds, and the distinction is the whole point
 * of this object:
 *
 *  - **Blocking** means physically impossible. One room cannot hold two classes
 *    at once and one lecturer cannot stand in two rooms at once. No campus
 *    policy makes these workable, so the service refuses them.
 *
 *  - **Non-blocking** means a judgement the campus is entitled to make. Two
 *    electives overlapping is normal; a class slightly larger than its room is
 *    something a registrar decides about, not something software should forbid.
 *
 * Software that blocks the second kind gets worked around — usually by
 * scheduling outside the system entirely.
 */
final readonly class BentrokJadwal
{
    public function __construct(
        public string $jenis,
        public string $pesan,
        public bool $menghalangi,
    ) {}

    public static function ruang(string $pesan): self
    {
        return new self('ruang', $pesan, true);
    }

    public static function dosen(string $pesan): self
    {
        return new self('dosen', $pesan, true);
    }

    /**
     * The same person booked twice at once.
     *
     * Only reachable for examinations — a student cannot be double-booked by
     * the class timetable, which schedules offerings rather than people.
     */
    public static function mahasiswa(string $pesan): self
    {
        return new self('mahasiswa', $pesan, true);
    }

    public static function kapasitas(string $pesan): self
    {
        return new self('kapasitas', $pesan, false);
    }

    public static function kohor(string $pesan): self
    {
        return new self('kohor', $pesan, false);
    }
}

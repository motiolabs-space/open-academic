<?php

declare(strict_types=1);

namespace App\Services\Akademik;

use App\Enums\KrsStatus;
use App\Exceptions\AturanAkademikException;
use App\Models\Akademik\KelasKuliah;
use App\Models\Akademik\Krs;
use App\Models\Akademik\MataKuliah;
use App\Models\Akademik\PaketKuliah;
use Illuminate\Support\Collection;

/**
 * Filling a study plan from a package instead of by choosing.
 *
 * Normal in vocational and diploma programmes: a cohort moves through a fixed
 * sequence together, and the plan is issued rather than assembled. What changes
 * is **who does the choosing** — not which rules apply. Every refusal
 * `KrsService::tambahKelas` makes still stands, and this deliberately delegates
 * to it rather than writing rows directly.
 *
 * That matters more than it looks. A package that inserted `krs_detail` itself
 * would bypass the quota lock, the timetable clash check, the prerequisite rule,
 * and the double-counting guard — and it would do so silently, for an entire
 * cohort at once.
 */
class PaketKuliahService
{
    public function __construct(private readonly KrsService $krs) {}

    /**
     * Fills a study plan from the package for the student's semester.
     *
     * Returns what happened per course rather than a bare count: a package where
     * two of eight courses could not be added is the normal case — a repeat
     * student already holds one, a class is full — and an operator needs to know
     * which two.
     *
     * @return array<string, mixed>
     */
    public function terapkan(Krs $krs): array
    {
        if ($krs->status !== KrsStatus::Draft) {
            throw new AturanAkademikException(
                'Paket hanya dapat diterapkan pada rencana studi yang masih berstatus draf.',
            );
        }

        $mahasiswa = $krs->mahasiswa;

        $paket = PaketKuliah::untuk(
            (int) $mahasiswa->kurikulum_id,
            $mahasiswa->konsentrasi_id,
            (int) $krs->semester_ke,
        );

        if ($paket === null) {
            throw new AturanAkademikException(sprintf(
                'Belum ada paket kuliah untuk semester %d pada kurikulum mahasiswa ini.',
                $krs->semester_ke,
            ));
        }

        $hasil = ['paket' => $paket, 'ditambahkan' => 0, 'dilewati' => []];

        foreach ($this->kelasUntuk($paket, $krs->tahun_akademik_id) as $baris) {
            [$mataKuliah, $kelas] = $baris;

            if ($kelas === null) {
                $hasil['dilewati'][] = [
                    'mata_kuliah' => $mataKuliah->nama,
                    'alasan' => 'Belum ada kelas yang dibuka pada semester ini.',
                ];

                continue;
            }

            try {
                $this->krs->tambahKelas($krs->refresh(), $kelas);
                $hasil['ditambahkan']++;
            } catch (AturanAkademikException $e) {
                /*
                 * Recorded and stepped over, not aborted.
                 *
                 * One student in a cohort of forty having already passed one
                 * course must not stop the other seven from being added — and
                 * an operator running this for a whole intake needs the reasons,
                 * not a stack trace on the first exception.
                 */
                $hasil['dilewati'][] = [
                    'mata_kuliah' => $mataKuliah->nama,
                    'alasan' => $e->getMessage(),
                ];
            }
        }

        return $hasil;
    }

    /** What a package would add, without adding it. */
    public function pratinjau(Krs $krs): array
    {
        $mahasiswa = $krs->mahasiswa;

        $paket = PaketKuliah::untuk(
            (int) $mahasiswa->kurikulum_id,
            $mahasiswa->konsentrasi_id,
            (int) $krs->semester_ke,
        );

        if ($paket === null) {
            return ['paket' => null, 'baris' => collect()];
        }

        return [
            'paket' => $paket,
            'baris' => collect($this->kelasUntuk($paket, $krs->tahun_akademik_id))
                ->map(fn (array $b): array => [
                    'mata_kuliah' => $b[0],
                    'kelas' => $b[1],
                ]),
        ];
    }

    /**
     * Pairs each package course with an open class, or null.
     *
     * One query for all of them. The alternative — looking up a class per course
     * inside the loop — is the shape that turns a forty-student intake run into
     * several thousand queries.
     *
     * @return array<int, array{0: MataKuliah, 1: ?KelasKuliah}>
     */
    private function kelasUntuk(PaketKuliah $paket, int $termId): array
    {
        $paket->loadMissing('mataKuliah');

        $kelas = KelasKuliah::query()
            // Eager-loaded because tambahKelas() reads the course off the class
            // for its own rules; without this the strict-mode guard fires on the
            // first row, which is exactly the N+1 it exists to catch.
            ->with(['mataKuliah', 'jadwal'])
            ->where('tahun_akademik_id', $termId)
            ->whereIn('mata_kuliah_id', $paket->mataKuliah->pluck('id'))
            ->orderBy('nama')
            ->get()
            ->groupBy('mata_kuliah_id');

        return $paket->mataKuliah
            ->map(fn ($mk): array => [
                $mk,

                // The first class with room left. A packaged cohort usually has
                // exactly one, but a large intake is split and the second half
                // must land in the second class rather than nowhere.
                ($kelas[$mk->id] ?? collect())
                    ->first(fn (KelasKuliah $k): bool => $k->terisi < $k->kuota),
            ])
            ->all();
    }

    /**
     * Whether a student's programme issues packages rather than taking choices.
     */
    public function berpaket(Krs $krs): bool
    {
        return $krs->mahasiswa->prodi?->mode_krs === 'paket';
    }

    /** @return Collection<int, PaketKuliah> */
    public function daftar(int $kurikulumId): Collection
    {
        return PaketKuliah::query()
            ->with(['mataKuliah', 'konsentrasi'])
            ->where('kurikulum_id', $kurikulumId)
            ->orderBy('semester_ke')
            ->get();
    }
}

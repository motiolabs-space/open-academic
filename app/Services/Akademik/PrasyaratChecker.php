<?php

declare(strict_types=1);

namespace App\Services\Akademik;

use App\Models\Akademik\MataKuliah;
use App\Models\Akademik\Nilai;
use App\Models\Kemahasiswaan\Mahasiswa;
use Illuminate\Support\Collection;

/**
 * Decides whether a student has cleared a course's prerequisites.
 *
 * A prerequisite counts as cleared only when the grade is *finalised* and meets
 * the configured minimum letter. A course still being graded does not unlock
 * anything — otherwise a student could chain two courses in one term on the
 * strength of a grade that has not been awarded yet.
 */
class PrasyaratChecker
{
    /** @var array<int, Collection<int, int>> mahasiswa id => course ids passed */
    private array $memo = [];

    /**
     * Prerequisites the student has not cleared, by course name.
     *
     * @return array<int, string> empty when everything is satisfied
     */
    public function belumTerpenuhi(Mahasiswa $mahasiswa, MataKuliah $mataKuliah): array
    {
        $prasyarat = $mataKuliah->prasyarat()->wherePivot('jenis', 'prasyarat')->get();

        if ($prasyarat->isEmpty()) {
            return [];
        }

        $lulus = $this->mataKuliahLulus($mahasiswa);

        return $prasyarat
            ->reject(fn (MataKuliah $syarat): bool => $lulus->contains($syarat->id))
            ->pluck('nama')
            ->values()
            ->all();
    }

    public function terpenuhi(Mahasiswa $mahasiswa, MataKuliah $mataKuliah): bool
    {
        return $this->belumTerpenuhi($mahasiswa, $mataKuliah) === [];
    }

    /** Whether the student already passed this exact course. */
    public function sudahLulus(Mahasiswa $mahasiswa, MataKuliah $mataKuliah): bool
    {
        return $this->mataKuliahLulus($mahasiswa)->contains($mataKuliah->id);
    }

    /**
     * Course ids the student has passed with a grade good enough to satisfy a
     * prerequisite. Memoised per student for the request — a KRS catalogue
     * checks this once per row.
     *
     * @return Collection<int, int>
     */
    private function mataKuliahLulus(Mahasiswa $mahasiswa): Collection
    {
        if (isset($this->memo[$mahasiswa->id])) {
            return $this->memo[$mahasiswa->id];
        }

        $nilai = Nilai::query()
            ->with('kelasKuliah:id,mata_kuliah_id')
            ->where('mahasiswa_id', $mahasiswa->id)
            ->final()
            ->whereNotNull('nilai_huruf')
            ->get();

        return $this->memo[$mahasiswa->id] = $nilai
            ->filter(fn (Nilai $baris): bool => $baris->nilai_huruf->satisfiesPrerequisite())
            ->map(fn (Nilai $baris): int => (int) $baris->kelasKuliah->mata_kuliah_id)
            ->unique()
            ->values();
    }

    /** Drops the memo — required after a grade is finalised within one request. */
    public function lupakan(?Mahasiswa $mahasiswa = null): void
    {
        if ($mahasiswa === null) {
            $this->memo = [];

            return;
        }

        unset($this->memo[$mahasiswa->id]);
    }
}

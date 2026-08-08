<?php

declare(strict_types=1);

namespace App\Services\Akademik;

use App\Exceptions\AturanAkademikException;
use App\Models\Akademik\MataKuliah;
use App\Models\Sdm\Staff;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Course equivalence across curriculum revisions.
 *
 * Every curriculum replacement creates the same problem: a student who passed
 * "Algoritma & Pemrograman" under the 2018 syllabus must not be made to sit
 * "Dasar Pemrograman" under the 2026 one. Without this, the campus solves it by
 * hand — an administrator ticking exemptions one student at a time, which is
 * both slow and unauditable.
 *
 * **The direction matters.** Recognising the old course as the new one does not
 * imply the reverse: the replacement may cover more ground, and accepting it
 * backwards would let a current student skip a prerequisite the old syllabus
 * never taught. A campus that means both directions records both rows.
 *
 * Equivalence is followed **transitively**, because curricula are replaced more
 * than once: 2018 → 2022 → 2026 is an ordinary shape, and a student from 2018
 * would otherwise be recognised only one revision forward.
 */
class PadananMataKuliah
{
    /** @var array<int, array<int, int>> memoised per request */
    private array $cache = [];

    /**
     * Records that passing one course counts as passing another.
     *
     * @throws AturanAkademikException on a self-reference or a cycle
     */
    public function tetapkan(
        MataKuliah $mataKuliah,
        MataKuliah $diakuiSebagai,
        ?Staff $staff = null,
        ?string $catatan = null,
    ): void {
        if ($mataKuliah->id === $diakuiSebagai->id) {
            throw new AturanAkademikException(
                'Mata kuliah tidak dapat dipadankan dengan dirinya sendiri.',
            );
        }

        /*
         * A cycle would make every course in the ring equivalent to every
         * other, which is almost never what anybody meant and is impossible to
         * spot once it exists — the resolver would simply return more and more
         * courses as "already passed".
         */
        if ($this->menuju($diakuiSebagai->id, $mataKuliah->id)) {
            throw new AturanAkademikException(sprintf(
                'Padanan ini membentuk lingkaran: %s sudah diakui sebagai %s, langsung atau lewat rantai padanan.',
                $diakuiSebagai->kode,
                $mataKuliah->kode,
            ));
        }

        DB::table('mata_kuliah_padanan')->updateOrInsert(
            [
                'mata_kuliah_id' => $mataKuliah->id,
                'diakui_sebagai_id' => $diakuiSebagai->id,
            ],
            [
                'catatan' => $catatan,
                'ditetapkan_by_staff_id' => $staff?->id,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        $this->lupakan();
    }

    public function hapus(int $mataKuliahId, int $diakuiSebagaiId): void
    {
        DB::table('mata_kuliah_padanan')
            ->where('mata_kuliah_id', $mataKuliahId)
            ->where('diakui_sebagai_id', $diakuiSebagaiId)
            ->delete();

        $this->lupakan();
    }

    /**
     * Every course a set of passed courses is recognised as, including the
     * originals.
     *
     * Given as a set operation rather than one course at a time, because the
     * caller that matters — "what has this student passed" — always has a whole
     * transcript in hand, and resolving one at a time would walk the equivalence
     * table once per course.
     *
     * @param iterable<int, int> $mataKuliahId
     * @return Collection<int, int>
     */
    public function perluas(iterable $mataKuliahId): Collection
    {
        $peta = $this->peta();
        $hasil = [];
        $antrean = collect($mataKuliahId)->all();

        while ($antrean !== []) {
            $id = array_pop($antrean);

            if (isset($hasil[$id])) {
                continue;
            }

            $hasil[$id] = true;

            foreach ($peta[$id] ?? [] as $berikutnya) {
                if (!isset($hasil[$berikutnya])) {
                    $antrean[] = $berikutnya;
                }
            }
        }

        return collect(array_keys($hasil));
    }

    /**
     * Courses recognised as one target, for showing on a screen.
     *
     * The reverse direction of `perluas`: "which older courses satisfy this
     * one".
     *
     * @return Collection<int, MataKuliah>
     */
    public function diterimaUntuk(MataKuliah $mataKuliah): Collection
    {
        $sumber = DB::table('mata_kuliah_padanan')
            ->where('diakui_sebagai_id', $mataKuliah->id)
            ->pluck('mata_kuliah_id');

        return MataKuliah::whereIn('id', $sumber)->orderBy('kode')->get();
    }

    /** @return Collection<int, object> */
    public function daftar(?int $prodiId = null): Collection
    {
        return DB::table('mata_kuliah_padanan as p')
            ->join('mata_kuliah as asal', 'asal.id', '=', 'p.mata_kuliah_id')
            ->join('mata_kuliah as tujuan', 'tujuan.id', '=', 'p.diakui_sebagai_id')
            ->when($prodiId !== null, fn ($q) => $q->where(fn ($s) => $s
                ->where('asal.prodi_id', $prodiId)
                ->orWhere('tujuan.prodi_id', $prodiId)))
            ->orderBy('asal.kode')
            ->select([
                'p.mata_kuliah_id', 'p.diakui_sebagai_id', 'p.catatan',
                'asal.kode as asal_kode', 'asal.nama as asal_nama',
                'tujuan.kode as tujuan_kode', 'tujuan.nama as tujuan_nama',
            ])
            ->get();
    }

    /** Drops the memoised graph. Call after any write. */
    public function lupakan(): void
    {
        $this->cache = [];
    }

    /** Whether following equivalences from one course reaches another. */
    private function menuju(int $dari, int $ke): bool
    {
        return $this->perluas([$dari])->contains($ke);
    }

    /**
     * The whole equivalence graph, loaded once.
     *
     * Small by nature — a campus has tens of these, not thousands — so loading
     * it whole beats a recursive query, and keeps the resolver portable across
     * MySQL and PostgreSQL rather than depending on a recursive CTE.
     *
     * @return array<int, array<int, int>>
     */
    private function peta(): array
    {
        if ($this->cache !== []) {
            return $this->cache;
        }

        return $this->cache = DB::table('mata_kuliah_padanan')
            ->get(['mata_kuliah_id', 'diakui_sebagai_id'])
            ->groupBy('mata_kuliah_id')
            ->map(fn (Collection $g): array => $g->pluck('diakui_sebagai_id')->map(intval(...))->all())
            ->all();
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Akademik;

use App\Models\Akademik\CapaianCplMahasiswa;
use App\Models\Akademik\CapaianCpmkMahasiswa;
use App\Models\Akademik\Cpmk;
use App\Models\Akademik\KelasKuliah;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\ProdiCpl;
use App\Models\Kemahasiswaan\Mahasiswa;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Reading stored attainment back: per class, per programme cohort, per student.
 *
 * Nothing here computes from marks. Every figure is either a stored row or an
 * aggregate over stored rows — the cross-course CPL figure is
 * SUM(nilai × sks) / SUM(sks) in one grouped query. A screen that rebuilt
 * attainment from components would disagree with the stored figure the moment
 * a correction landed, and the stored one is the one that was audited.
 *
 * Operational recaps only. There is no target, no ranking against other
 * programmes, and no accreditation score here (CLAUDE.md §1, §6).
 */
class RekapCapaianService
{
    public function ambang(): float
    {
        return (float) config('academic.cpl.ambang_penguasaan', 65);
    }

    /**
     * One class: every participant against every CPMK and CPL.
     *
     * @return array{terhitung: bool, ambang: float, cpmk: Collection<int, Cpmk>, cpl: Collection<int, ProdiCpl>, mahasiswa: Collection<int, array<string, mixed>>, ringkas_cpmk: array<int, array<string, mixed>>, ringkas_cpl: array<int, array<string, mixed>>}
     */
    public function kelas(KelasKuliah $kelas): array
    {
        $ambang = $this->ambang();

        $barisCpmk = CapaianCpmkMahasiswa::where('kelas_kuliah_id', $kelas->id)->get();
        $barisCpl = CapaianCplMahasiswa::where('kelas_kuliah_id', $kelas->id)->get();

        $cpmk = Cpmk::query()
            ->whereIn('id', $barisCpmk->pluck('cpmk_id')->unique())
            ->orderBy('urutan')
            ->orderBy('kode')
            ->get();

        $cpl = ProdiCpl::query()
            ->whereIn('id', $barisCpl->pluck('prodi_cpl_id')->unique())
            ->orderBy('kode')
            ->get();

        $mahasiswa = Mahasiswa::query()
            ->whereIn('id', $barisCpmk->pluck('mahasiswa_id')->unique())
            ->orderBy('nim')
            ->get(['id', 'uuid', 'nim', 'nama']);

        $cpmkPer = $barisCpmk->groupBy('mahasiswa_id');
        $cplPer = $barisCpl->groupBy('mahasiswa_id');

        return [
            'terhitung' => $barisCpmk->isNotEmpty(),
            'ambang' => $ambang,
            'cpmk' => $cpmk,
            'cpl' => $cpl,

            'mahasiswa' => $mahasiswa->map(fn (Mahasiswa $m): array => [
                'mahasiswa' => $m,
                'cpmk' => ($cpmkPer->get($m->id) ?? collect())->mapWithKeys(fn ($b): array => [$b->cpmk_id => $b->nilai])->all(),
                'cakupan' => ($cpmkPer->get($m->id) ?? collect())->mapWithKeys(fn ($b): array => [$b->cpmk_id => $b->cakupan])->all(),
                'cpl' => ($cplPer->get($m->id) ?? collect())->mapWithKeys(fn ($b): array => [$b->prodi_cpl_id => $b->nilai])->all(),
            ]),

            'ringkas_cpmk' => $this->ringkas($barisCpmk->groupBy('cpmk_id'), $ambang),
            'ringkas_cpl' => $this->ringkas($barisCpl->groupBy('prodi_cpl_id'), $ambang),
        ];
    }

    /**
     * One programme, optionally one intake year: each student's CPL figure
     * across every course they finished, and a summary per CPL.
     *
     * @return array{ambang: float, angkatan: array<int, int>, cpl: Collection<int, ProdiCpl>, mahasiswa: Collection<int, array<string, mixed>>, ringkas: array<int, array<string, mixed>>}
     */
    public function prodi(Prodi $prodi, ?int $angkatan = null): array
    {
        $ambang = $this->ambang();

        $tersedia = DB::table('capaian_cpl_mahasiswa')
            ->join('mahasiswa', 'mahasiswa.id', '=', 'capaian_cpl_mahasiswa.mahasiswa_id')
            ->where('mahasiswa.prodi_id', $prodi->id)
            ->distinct()
            ->orderByDesc('mahasiswa.angkatan')
            ->pluck('mahasiswa.angkatan')
            ->map(fn ($a): int => (int) $a)
            ->all();

        $agregat = $this->agregatCpl()
            ->where('mahasiswa.prodi_id', $prodi->id)
            ->when($angkatan !== null, fn ($q) => $q->where('mahasiswa.angkatan', $angkatan))
            ->get();

        $cpl = ProdiCpl::query()
            ->where('prodi_id', $prodi->id)
            ->whereIn('id', $agregat->pluck('prodi_cpl_id')->unique())
            ->orderBy('kode')
            ->get();

        $mahasiswa = Mahasiswa::query()
            ->whereIn('id', $agregat->pluck('mahasiswa_id')->unique())
            ->orderBy('nim')
            ->get(['id', 'uuid', 'nim', 'nama', 'angkatan']);

        $per = $agregat->groupBy('mahasiswa_id');

        return [
            'ambang' => $ambang,
            'angkatan' => $tersedia,
            'cpl' => $cpl,

            'mahasiswa' => $mahasiswa->map(fn (Mahasiswa $m): array => [
                'mahasiswa' => $m,
                'cpl' => ($per->get($m->id) ?? collect())
                    ->mapWithKeys(fn ($b): array => [(int) $b->prodi_cpl_id => round((float) $b->nilai, 2)])
                    ->all(),
            ]),

            'ringkas' => $this->ringkas(
                $agregat->map(fn ($b): object => (object) [
                    'kunci' => (int) $b->prodi_cpl_id,
                    'nilai' => round((float) $b->nilai, 2),
                ])->groupBy('kunci'),
                $ambang,
            ),
        ];
    }

    /**
     * One student: CPL across courses, and CPMK course by course.
     *
     * @return array{terhitung: bool, ambang: float, cpl: Collection<int, array<string, mixed>>, mata_kuliah: Collection<int, array<string, mixed>>}
     */
    public function mahasiswa(Mahasiswa $mahasiswa): array
    {
        $ambang = $this->ambang();

        $agregat = $this->agregatCpl()
            ->where('capaian_cpl_mahasiswa.mahasiswa_id', $mahasiswa->id)
            ->get()
            ->keyBy('prodi_cpl_id');

        if ($agregat->isEmpty()) {
            return ['terhitung' => false, 'ambang' => $ambang, 'cpl' => collect(), 'mata_kuliah' => collect()];
        }

        // Which courses fed each CPL, named — "diukur di tiga mata kuliah"
        // is what makes the figure something an advisor can discuss.
        $sumber = DB::table('capaian_cpl_mahasiswa')
            ->join('kelas_kuliah', 'kelas_kuliah.id', '=', 'capaian_cpl_mahasiswa.kelas_kuliah_id')
            ->join('mata_kuliah', 'mata_kuliah.id', '=', 'kelas_kuliah.mata_kuliah_id')
            ->where('capaian_cpl_mahasiswa.mahasiswa_id', $mahasiswa->id)
            ->orderBy('mata_kuliah.nama')
            ->get(['capaian_cpl_mahasiswa.prodi_cpl_id', 'mata_kuliah.nama'])
            ->groupBy('prodi_cpl_id');

        $cpl = ProdiCpl::whereIn('id', $agregat->keys())->orderBy('kode')->get();

        $cpmk = CapaianCpmkMahasiswa::query()
            ->with(['cpmk', 'kelasKuliah.mataKuliah', 'kelasKuliah.tahunAkademik'])
            ->where('mahasiswa_id', $mahasiswa->id)
            ->get()
            ->groupBy('kelas_kuliah_id');

        return [
            'terhitung' => true,
            'ambang' => $ambang,

            'cpl' => $cpl->map(function (ProdiCpl $c) use ($agregat, $sumber, $ambang): array {
                $nilai = round((float) $agregat->get($c->id)->nilai, 2);

                return [
                    'cpl' => $c,
                    'nilai' => $nilai,
                    'tercapai' => $nilai >= $ambang,
                    'mata_kuliah' => ($sumber->get($c->id) ?? collect())->pluck('nama')->unique()->values(),
                ];
            })->sortBy('nilai')->values(),

            'mata_kuliah' => $cpmk
                ->map(fn (Collection $baris): array => [
                    'kelas' => $baris->first()->kelasKuliah,
                    'cpmk' => $baris
                        ->sortBy(fn (CapaianCpmkMahasiswa $b): string => sprintf('%05d%s', $b->cpmk->urutan, $b->cpmk->kode))
                        ->map(fn (CapaianCpmkMahasiswa $b): array => [
                            'cpmk' => $b->cpmk,
                            'nilai' => $b->nilai,
                            'cakupan' => $b->cakupan,
                            'tercapai' => $b->nilai >= $ambang,
                        ])
                        ->values(),
                ])
                ->sortByDesc(fn (array $b): string => $b['kelas']->tahunAkademik->kode.$b['kelas']->mataKuliah->nama)
                ->values(),
        ];
    }

    /**
     * CPL attainment per student programme-wide, for the LKPS arranger.
     *
     * Restricted to classes of the given terms so the table describes a
     * stated window, not "everything ever recorded".
     *
     * @param array<int, int> $tahunMulai academic years by starting year
     * @return Collection<int, array{cpl: ProdiCpl, terukur: int, rerata: float, tercapai: int}>
     */
    public function perCplProdi(Prodi $prodi, array $tahunMulai): Collection
    {
        $ambang = $this->ambang();

        $agregat = $this->agregatCpl()
            ->join('kelas_kuliah', 'kelas_kuliah.id', '=', 'capaian_cpl_mahasiswa.kelas_kuliah_id')
            ->join('tahun_akademik', 'tahun_akademik.id', '=', 'kelas_kuliah.tahun_akademik_id')
            ->where('mahasiswa.prodi_id', $prodi->id)
            ->whereIn('tahun_akademik.tahun_mulai', $tahunMulai)
            ->get()
            ->groupBy('prodi_cpl_id');

        return ProdiCpl::query()
            ->whereIn('id', $agregat->keys())
            ->orderBy('kode')
            ->get()
            ->map(function (ProdiCpl $c) use ($agregat, $ambang): array {
                $nilai = $agregat->get($c->id)->map(fn ($b): float => (float) $b->nilai);

                return [
                    'cpl' => $c,
                    'terukur' => $nilai->count(),
                    'rerata' => round((float) $nilai->avg(), 2),
                    'tercapai' => $nilai->filter(fn (float $n): bool => $n >= $ambang)->count(),
                ];
            });
    }

    /* ---------------------------------------------------------------------
     | Internals
     |-------------------------------------------------------------------- */

    /**
     * Credit-weighted CPL attainment per (student, CPL), as one grouped query.
     *
     * Lines without credits (sks 0) still count, at weight one, rather than
     * dividing by zero or vanishing.
     */
    private function agregatCpl(): Builder
    {
        return DB::table('capaian_cpl_mahasiswa')
            ->join('mahasiswa', 'mahasiswa.id', '=', 'capaian_cpl_mahasiswa.mahasiswa_id')
            ->groupBy('capaian_cpl_mahasiswa.mahasiswa_id', 'capaian_cpl_mahasiswa.prodi_cpl_id')
            ->selectRaw(
                'capaian_cpl_mahasiswa.mahasiswa_id, capaian_cpl_mahasiswa.prodi_cpl_id, '
                .'SUM(capaian_cpl_mahasiswa.nilai * CASE WHEN capaian_cpl_mahasiswa.sks > 0 THEN capaian_cpl_mahasiswa.sks ELSE 1 END) '
                .'/ SUM(CASE WHEN capaian_cpl_mahasiswa.sks > 0 THEN capaian_cpl_mahasiswa.sks ELSE 1 END) AS nilai, '
                .'COUNT(*) AS jumlah_mk'
            );
    }

    /**
     * Mean, count and how many reached the threshold, per key.
     *
     * @param Collection<int|string, Collection<int, object>> $kelompok
     * @return array<int, array{rerata: float, jumlah: int, tercapai: int}>
     */
    private function ringkas(Collection $kelompok, float $ambang): array
    {
        return $kelompok->map(function (Collection $g) use ($ambang): array {
            $nilai = $g->map(fn ($b): float => (float) $b->nilai);

            return [
                'rerata' => round((float) $nilai->avg(), 2),
                'jumlah' => $nilai->count(),
                'tercapai' => $nilai->filter(fn (float $n): bool => $n >= $ambang)->count(),
            ];
        })->all();
    }
}

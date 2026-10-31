<?php

declare(strict_types=1);

namespace App\Services\Akademik;

use App\DTOs\Akademik\PemetaanCapaianData;
use App\Exceptions\AturanAkademikException;
use App\Exceptions\PenilaianException;
use App\Models\Akademik\CapaianCplMahasiswa;
use App\Models\Akademik\CapaianCpmkMahasiswa;
use App\Models\Akademik\Cpmk;
use App\Models\Akademik\CpmkVersi;
use App\Models\Akademik\KelasKuliah;
use App\Models\Akademik\KomponenNilai;
use App\Models\Akademik\KomponenNilaiCpmk;
use App\Models\Akademik\KrsDetail;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Grading by outcome: what each component measures, and what that adds up to.
 *
 * **The arithmetic, in one place** (ADR-038 §3). For one student in one class:
 *
 *   - each mapping row carries weight *component weight × share of the
 *     component*, so a midterm worth 30% of the grade, 60% of which measures
 *     Sub-CPMK 1.2, gives that sub-CPMK 18 units;
 *   - a sub-CPMK (or a CPMK without parts) is the weighted mean of the scores
 *     measuring it;
 *   - a CPMK is the weighted mean of its sub-CPMK by their weight in it,
 *     over the parts that were measured — `cakupan` records how much of the
 *     whole that was, rather than counting an unassessed part as zero against
 *     the student;
 *   - a CPL, within this course, is the weighted mean of the CPMK feeding it
 *     by their CPMK→CPL weights.
 *
 * Computed once, when the class is finalised, and stored. The figure on any
 * screen afterwards is the stored one; it changes only when a grade is
 * corrected through PenilaianService, which recomputes the corrected line.
 */
class CapaianService
{
    public function __construct(private readonly CpmkService $cpmk) {}

    /**
     * Saves which outcomes each component measures, replacing the class's
     * previous mapping.
     *
     * A component is either unmapped or mapped in full: its shares total 100.
     * A partly mapped exam would leave some of its marks measuring nothing,
     * and no screen could say which.
     *
     * @throws AturanAkademikException
     */
    public function petakan(KelasKuliah $kelas, PemetaanCapaianData $data): int
    {
        if ($kelas->status_nilai === 'final') {
            throw PenilaianException::kelasSudahFinal();
        }

        $versi = $this->cpmk->versiUntukKelas($kelas)
            ?? throw new AturanAkademikException(__('capaian.galat.tanpa_versi'));

        $sasaran = $this->cpmk->sasaran($versi);
        $komponen = $kelas->komponenNilai()->get()->keyBy('id');

        foreach ($data->porsi as $komponenId => $baris) {
            $satu = $komponen->get($komponenId)
                ?? throw new AturanAkademikException(__('capaian.galat.komponen_asing'));

            foreach (array_keys($baris) as $kunci) {
                if (!$sasaran->has($kunci)) {
                    throw new AturanAkademikException(__('capaian.galat.sasaran_asing'));
                }
            }

            if (($jumlah = array_sum($baris)) !== 100) {
                throw new AturanAkademikException(__('capaian.galat.porsi_bukan_seratus', [
                    'komponen' => $satu->nama,
                    'jumlah' => $jumlah,
                ]));
            }
        }

        return DB::transaction(function () use ($komponen, $data, $sasaran): int {
            // Deleted one by one so each removal reaches the audit trail.
            KomponenNilaiCpmk::query()
                ->whereIn('komponen_nilai_id', $komponen->keys())
                ->get()
                ->each->delete();

            foreach ($data->porsi as $komponenId => $baris) {
                foreach ($baris as $kunci => $porsi) {
                    $target = $sasaran->get($kunci);

                    KomponenNilaiCpmk::create([
                        'komponen_nilai_id' => $komponenId,
                        'cpmk_id' => $target['cpmk']->id,
                        'sub_cpmk_id' => $target['sub']?->id,
                        'porsi' => $porsi,
                    ]);
                }
            }

            return count($data->porsi);
        });
    }

    /**
     * Coverage of the class's evaluation plan against its outcomes.
     *
     * The warning the plan screen exists to show: a sub-CPMK no component
     * measures was promised and never assessed — the gap an accreditation
     * visit looks for, visible while the lecturer can still close it.
     *
     * Pass the components when the caller already holds them — the grade
     * sheet does — so the mapping is one eager load on top, not a re-read.
     *
     * @param Collection<int, KomponenNilai>|null $komponen
     * @return array{versi: CpmkVersi|null, versi_lama: CpmkVersi|null, sasaran: Collection<string, array<string, mixed>>, matriks: array<int, array<string, int>>, tak_terukur: Collection<string, array<string, mixed>>, komponen_tanpa: Collection<int, KomponenNilai>}
     */
    public function cakupan(KelasKuliah $kelas, ?Collection $komponen = null): array
    {
        $idTerpetakan = $this->cpmk->idVersiTerpetakan($kelas);

        $versi = $kelas->status_nilai === 'final'
            ? ($idTerpetakan === null ? null : CpmkVersi::find($idTerpetakan))
            : $this->cpmk->versiUntukKelas($kelas);

        $komponen = ($komponen ?? $kelas->komponenNilai()->get())->loadMissing('pemetaanCpmk');

        // A mapping against a superseded version is shown as stale, not mixed
        // into the matrix of the current one.
        $berlakuSama = $idTerpetakan === null || $versi === null || $idTerpetakan === $versi->id;
        $terpetakan = $berlakuSama ? null : CpmkVersi::find($idTerpetakan);

        $matriks = $komponen->mapWithKeys(fn (KomponenNilai $k): array => [
            $k->id => $berlakuSama
                ? $k->pemetaanCpmk->mapWithKeys(fn (KomponenNilaiCpmk $p): array => [$p->sasaran() => $p->porsi])->all()
                : [],
        ])->all();

        $sasaran = $versi === null ? collect() : $this->cpmk->sasaran($versi);

        $terukur = collect($matriks)->flatMap(fn (array $b): array => array_keys($b))->unique()->flip();

        return [
            'versi' => $versi,
            'versi_lama' => $terpetakan,
            'sasaran' => $sasaran,
            'matriks' => $matriks,
            'tak_terukur' => $sasaran->reject(fn (array $s, string $kunci): bool => $terukur->has($kunci)),
            'komponen_tanpa' => $komponen->filter(fn (KomponenNilai $k): bool => ($matriks[$k->id] ?? []) === [])->values(),
        ];
    }

    /**
     * Computes and stores attainment for every graded line of a class.
     *
     * Called inside the finalisation transaction. Returns the number of lines
     * that received figures — zero when no component is mapped, which is not
     * an error: an unmapped class still has grades, it just says nothing about
     * outcomes.
     */
    public function hitungKelas(KelasKuliah $kelas): int
    {
        $pemetaan = $this->pemetaan($kelas);

        if ($pemetaan->isEmpty()) {
            return 0;
        }

        $cpmk = $this->cpmkTerpetakan($pemetaan);

        $detail = $kelas->krsDetail()
            ->whereNull('krs_detail.aktivitas_mahasiswa_id')
            ->with(['krs:id,mahasiswa_id', 'nilaiKomponen'])
            ->get();

        $ada = CapaianCpmkMahasiswa::where('kelas_kuliah_id', $kelas->id)->exists()
            || CapaianCplMahasiswa::where('kelas_kuliah_id', $kelas->id)->exists();

        $jumlah = 0;
        $barisCpmk = [];
        $barisCpl = [];

        foreach ($detail as $d) {
            $hasil = $this->hitung($pemetaan, $cpmk, $d->nilaiKomponen->pluck('nilai', 'komponen_nilai_id'));

            if ($hasil['cpmk'] === []) {
                continue;
            }

            $jumlah++;

            if ($ada) {
                $this->simpanBaris($d, $kelas, $hasil);

                continue;
            }

            [$cpmkBaru, $cplBaru] = $this->barisBaru($d, $kelas, $hasil);
            array_push($barisCpmk, ...$cpmkBaru);
            array_push($barisCpl, ...$cplBaru);
        }

        /*
         * First computation: inserted in bulk, audited once.
         *
         * A class of forty with five CPMK and three CPL is three hundred and
         * twenty rows, each of which would otherwise queue its own audit job
         * for a value nobody has changed yet. The event worth recording is the
         * computation; a later change to any one row — through correction — is
         * audited row by row with its old and new value.
         */
        foreach (array_chunk($barisCpmk, 500) as $potong) {
            CapaianCpmkMahasiswa::insert($potong);
        }

        foreach (array_chunk($barisCpl, 500) as $potong) {
            CapaianCplMahasiswa::insert($potong);
        }

        if ($jumlah > 0) {
            $kelas->recordActivity('capaian_dihitung', __('capaian.audit.dihitung', [
                'jumlah' => $jumlah,
                'cpmk' => count($barisCpmk),
                'cpl' => count($barisCpl),
            ]));
        }

        return $jumlah;
    }

    /**
     * Recomputes one line after its marks were corrected.
     *
     * Goes through the models, so every figure that moves is written to the
     * audit trail with its old and new value.
     */
    public function hitungUntuk(KrsDetail $detail): void
    {
        $detail->loadMissing(['kelasKuliah', 'krs']);

        $pemetaan = $this->pemetaan($detail->kelasKuliah);

        if ($pemetaan->isEmpty()) {
            return;
        }

        $skor = $detail->nilaiKomponen()->pluck('nilai', 'komponen_nilai_id');

        $this->simpanBaris(
            $detail,
            $detail->kelasKuliah,
            $this->hitung($pemetaan, $this->cpmkTerpetakan($pemetaan), $skor),
        );
    }

    /**
     * The arithmetic described on the class, for one student.
     *
     * @param Collection<int, object> $pemetaan
     * @param Collection<int, Cpmk> $cpmk keyed by id
     * @param Collection<int|string, mixed> $skor komponen id => score
     * @return array{cpmk: array<int, array{nilai: float, cakupan: int}>, cpl: array<int, float>}
     */
    public function hitung(Collection $pemetaan, Collection $cpmk, Collection $skor): array
    {
        // Weighted mean per measured target: "s:{sub}" or "c:{cpmk}".
        $per = [];

        foreach ($pemetaan as $p) {
            $nilai = $skor->get($p->komponen_nilai_id);

            if ($nilai === null) {
                continue;
            }

            $w = (int) $p->bobot * (int) $p->porsi;

            if ($w <= 0) {
                continue;
            }

            $kunci = $p->sub_cpmk_id !== null ? 's:'.$p->sub_cpmk_id : 'c:'.$p->cpmk_id;

            $per[$kunci]['w'] = ($per[$kunci]['w'] ?? 0) + $w;
            $per[$kunci]['ws'] = ($per[$kunci]['ws'] ?? 0.0) + $w * (float) $nilai;
        }

        $rerata = array_map(fn (array $b): float => $b['ws'] / $b['w'], $per);

        $hasilCpmk = [];

        foreach ($cpmk as $c) {
            if ($c->sub->isEmpty()) {
                if (isset($rerata['c:'.$c->id])) {
                    $hasilCpmk[$c->id] = ['nilai' => round($rerata['c:'.$c->id], 2), 'cakupan' => 100];
                }

                continue;
            }

            $bobot = 0;
            $jumlah = 0.0;

            foreach ($c->sub as $s) {
                if (!isset($rerata['s:'.$s->id]) || $s->bobot <= 0) {
                    continue;
                }

                $bobot += $s->bobot;
                $jumlah += $s->bobot * $rerata['s:'.$s->id];
            }

            if ($bobot > 0) {
                $hasilCpmk[$c->id] = [
                    'nilai' => round($jumlah / $bobot, 2),
                    'cakupan' => (int) min(100, $bobot),
                ];
            }
        }

        $perCpl = [];

        foreach ($cpmk as $c) {
            if (!isset($hasilCpmk[$c->id])) {
                continue;
            }

            foreach ($c->cpl as $cpl) {
                $b = (int) $cpl->pivot->bobot;

                if ($b <= 0) {
                    continue;
                }

                $perCpl[$cpl->id]['w'] = ($perCpl[$cpl->id]['w'] ?? 0) + $b;
                $perCpl[$cpl->id]['ws'] = ($perCpl[$cpl->id]['ws'] ?? 0.0) + $b * $hasilCpmk[$c->id]['nilai'];
            }
        }

        return [
            'cpmk' => $hasilCpmk,
            'cpl' => array_map(fn (array $b): float => round($b['ws'] / $b['w'], 2), $perCpl),
        ];
    }

    /* ---------------------------------------------------------------------
     | Internals
     |-------------------------------------------------------------------- */

    /**
     * The class's mapping rows with each component's weight, in one join.
     *
     * @return Collection<int, object>
     */
    private function pemetaan(KelasKuliah $kelas): Collection
    {
        return DB::table('komponen_nilai_cpmk')
            ->join('komponen_nilai', 'komponen_nilai.id', '=', 'komponen_nilai_cpmk.komponen_nilai_id')
            ->where('komponen_nilai.kelas_kuliah_id', $kelas->id)
            ->select([
                'komponen_nilai_cpmk.komponen_nilai_id',
                'komponen_nilai_cpmk.cpmk_id',
                'komponen_nilai_cpmk.sub_cpmk_id',
                'komponen_nilai_cpmk.porsi',
                'komponen_nilai.bobot',
            ])
            ->get();
    }

    /**
     * Every CPMK of the mapped version — not only the mapped ones, so a CPL
     * fed by an unmeasured CPMK is computed over the measured ones only.
     *
     * @param Collection<int, object> $pemetaan
     * @return Collection<int, Cpmk>
     */
    private function cpmkTerpetakan(Collection $pemetaan): Collection
    {
        $versiId = Cpmk::whereIn('id', $pemetaan->pluck('cpmk_id')->unique())->distinct()->pluck('cpmk_versi_id');

        return Cpmk::query()
            ->with(['sub', 'cpl'])
            ->whereIn('cpmk_versi_id', $versiId)
            ->get()
            ->keyBy('id');
    }

    /**
     * Rows for a first computation, ready for a bulk insert.
     *
     * @param array{cpmk: array<int, array{nilai: float, cakupan: int}>, cpl: array<int, float>} $hasil
     * @return array{0: list<array<string, mixed>>, 1: list<array<string, mixed>>}
     */
    private function barisBaru(KrsDetail $detail, KelasKuliah $kelas, array $hasil): array
    {
        $sekarang = now();
        $dasar = [
            'krs_detail_id' => $detail->id,
            'mahasiswa_id' => $detail->krs->mahasiswa_id,
            'kelas_kuliah_id' => $kelas->id,
            'created_at' => $sekarang,
            'updated_at' => $sekarang,
        ];

        $cpmk = [];

        foreach ($hasil['cpmk'] as $cpmkId => $b) {
            $cpmk[] = $dasar + [
                'uuid' => (string) Str::uuid(),
                'cpmk_id' => $cpmkId,
                'nilai' => $b['nilai'],
                'cakupan' => $b['cakupan'],
            ];
        }

        $cpl = [];

        foreach ($hasil['cpl'] as $cplId => $nilai) {
            $cpl[] = $dasar + [
                'uuid' => (string) Str::uuid(),
                'prodi_cpl_id' => $cplId,
                'nilai' => $nilai,
                'sks' => (int) $detail->sks,
            ];
        }

        return [$cpmk, $cpl];
    }

    /**
     * Writes one line's figures through the models: changed values are
     * updated (and audited), vanished ones deleted, new ones created.
     *
     * @param array{cpmk: array<int, array{nilai: float, cakupan: int}>, cpl: array<int, float>} $hasil
     */
    private function simpanBaris(KrsDetail $detail, KelasKuliah $kelas, array $hasil): void
    {
        DB::transaction(function () use ($detail, $kelas, $hasil): void {
            $dasar = [
                'mahasiswa_id' => $detail->krs->mahasiswa_id,
                'kelas_kuliah_id' => $kelas->id,
            ];

            $adaCpmk = CapaianCpmkMahasiswa::where('krs_detail_id', $detail->id)->get()->keyBy('cpmk_id');

            foreach ($hasil['cpmk'] as $cpmkId => $b) {
                $baris = $adaCpmk->pull($cpmkId)
                    ?? new CapaianCpmkMahasiswa(['krs_detail_id' => $detail->id, 'cpmk_id' => $cpmkId]);

                $baris->fill($dasar + ['nilai' => $b['nilai'], 'cakupan' => $b['cakupan']])->save();
            }

            $adaCpmk->each->delete();

            $adaCpl = CapaianCplMahasiswa::where('krs_detail_id', $detail->id)->get()->keyBy('prodi_cpl_id');

            foreach ($hasil['cpl'] as $cplId => $nilai) {
                $baris = $adaCpl->pull($cplId)
                    ?? new CapaianCplMahasiswa(['krs_detail_id' => $detail->id, 'prodi_cpl_id' => $cplId]);

                $baris->fill($dasar + ['nilai' => $nilai, 'sks' => (int) $detail->sks])->save();
            }

            $adaCpl->each->delete();
        });
    }
}

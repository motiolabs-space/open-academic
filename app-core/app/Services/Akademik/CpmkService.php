<?php

declare(strict_types=1);

namespace App\Services\Akademik;

use App\DTOs\Akademik\CpmkData;
use App\DTOs\Akademik\RumusanCpmkData;
use App\DTOs\Akademik\SubCpmkData;
use App\Enums\StatusCpmk;
use App\Exceptions\AturanAkademikException;
use App\Models\Akademik\Cpmk;
use App\Models\Akademik\CpmkVersi;
use App\Models\Akademik\KelasKuliah;
use App\Models\Akademik\Kurikulum;
use App\Models\Akademik\MataKuliah;
use App\Models\Akademik\ProdiCpl;
use App\Models\Akademik\Rps;
use App\Models\Akademik\SubCpmk;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\Staff;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * A course's outcome set: CPMK and sub-CPMK, versioned per curriculum.
 *
 * Two rules, both borrowed from the teaching plan because the reason is the
 * same:
 *
 *  1. **A version in force is frozen.** Grade components are mapped to its
 *     rows, and the attainment of every finalised class is stored against
 *     them. Editing it would move a mark already recorded from one outcome to
 *     another without anybody being able to say when. Revising means a new
 *     draft, copied from the one in force, published over it.
 *
 *  2. **The weights must close before publishing.** Sub-CPMK of one CPMK total
 *     100, and the CPMK feeding one CPL total 100. A set that does not close
 *     produces attainment figures that are averages of an unknown whole.
 *
 * Kept by the programme (staff) and by the lecturers who teach the course —
 * the people who write the RPS are the people who know what it promises.
 */
class CpmkService
{
    /**
     * The curriculum a course's outcomes are written in.
     *
     * A course can sit in more than one curriculum version. The one in force
     * for its programme wins, then the most recent.
     */
    public function kurikulumUntuk(MataKuliah $mataKuliah): ?Kurikulum
    {
        return $mataKuliah->kurikulum()
            ->where('kurikulum.prodi_id', $mataKuliah->prodi_id)
            ->reorder()
            ->orderByDesc('kurikulum.is_active')
            ->orderByDesc('kurikulum.tahun_mulai')
            ->first();
    }

    /** The version in force for a course, in its curriculum. */
    public function berlakuUntuk(MataKuliah $mataKuliah, ?Kurikulum $kurikulum = null): ?CpmkVersi
    {
        $kurikulum ??= $this->kurikulumUntuk($mataKuliah);

        if ($kurikulum === null) {
            return null;
        }

        return CpmkVersi::query()
            ->where('kurikulum_id', $kurikulum->id)
            ->where('mata_kuliah_id', $mataKuliah->id)
            ->aktif()
            ->first();
    }

    /**
     * The draft in progress, or a new one copied from the version in force.
     *
     * @throws AturanAkademikException when the course is not in that curriculum
     */
    public function draf(Kurikulum $kurikulum, MataKuliah $mataKuliah, Staff|Dosen|null $penyusun = null): CpmkVersi
    {
        if (!$kurikulum->mataKuliah()->whereKey($mataKuliah->id)->exists()) {
            throw new AturanAkademikException(__('capaian.galat.bukan_kurikulum'));
        }

        $draf = CpmkVersi::query()
            ->where('kurikulum_id', $kurikulum->id)
            ->where('mata_kuliah_id', $mataKuliah->id)
            ->where('status', StatusCpmk::Draft->value)
            ->first();

        if ($draf !== null) {
            return $draf;
        }

        $berlaku = $this->berlakuUntuk($mataKuliah, $kurikulum);

        $versiTerakhir = (int) CpmkVersi::withTrashed()
            ->where('kurikulum_id', $kurikulum->id)
            ->where('mata_kuliah_id', $mataKuliah->id)
            ->max('versi');

        return DB::transaction(function () use ($kurikulum, $mataKuliah, $penyusun, $berlaku, $versiTerakhir): CpmkVersi {
            $draf = CpmkVersi::create([
                'kurikulum_id' => $kurikulum->id,
                'mata_kuliah_id' => $mataKuliah->id,
                'versi' => $versiTerakhir + 1,
                'status' => StatusCpmk::Draft,
                'disusun_by_staff_id' => $penyusun instanceof Staff ? $penyusun->id : null,
                'disusun_by_dosen_id' => $penyusun instanceof Dosen ? $penyusun->id : null,
            ]);

            // A revision starts from what is in force: correcting one sub-CPMK
            // should not mean retyping the other eleven.
            if ($berlaku !== null) {
                $this->tulis($draf, $this->sebagaiData($berlaku->load(CpmkVersi::MUAT)));
            }

            return $draf->fresh(CpmkVersi::MUAT);
        });
    }

    /**
     * Replaces the whole set of a draft.
     *
     * Weights are not required to close here — a draft is allowed to be half
     * written. They are checked at publishing, where kekurangan() names every
     * gap at once.
     *
     * @throws AturanAkademikException
     */
    public function simpan(CpmkVersi $versi, RumusanCpmkData $data): CpmkVersi
    {
        $this->pastikanDraf($versi);

        $versi->loadMissing('kurikulum');

        $cplSah = ProdiCpl::where('prodi_id', $versi->kurikulum->prodi_id)->pluck('id')->flip();
        $kode = [];

        foreach ($data->cpmk as $cpmk) {
            if ($cpmk->kode === '' || $cpmk->rumusan === '') {
                throw new AturanAkademikException(__('capaian.galat.cpmk_tidak_lengkap'));
            }

            if (isset($kode[mb_strtolower($cpmk->kode)])) {
                throw new AturanAkademikException(__('capaian.galat.kode_ganda', ['kode' => $cpmk->kode]));
            }

            $kode[mb_strtolower($cpmk->kode)] = true;

            foreach (array_keys($cpmk->cpl) as $cplId) {
                if (!$cplSah->has($cplId)) {
                    // Another programme's outcome on this course's map would put
                    // a figure where it does not belong, invisibly.
                    throw new AturanAkademikException(__('capaian.galat.cpl_prodi_lain'));
                }
            }

            $kodeSub = [];

            foreach ($cpmk->sub as $sub) {
                if ($sub->kode === '' || isset($kodeSub[mb_strtolower($sub->kode)])) {
                    throw new AturanAkademikException(__('capaian.galat.kode_sub_ganda', ['kode' => $cpmk->kode]));
                }

                $kodeSub[mb_strtolower($sub->kode)] = true;
            }
        }

        DB::transaction(function () use ($versi, $data): void {
            // Draft rows are referenced by nothing — only versions in force can
            // be mapped or attached — so replacing them loses nothing.
            $versi->cpmk()->get()->each->delete();

            $this->tulis($versi, $data);
        });

        return $versi->fresh(CpmkVersi::MUAT);
    }

    /**
     * Everything that stops this version being published, as sentences.
     *
     * @return array<int, string>
     */
    public function kekurangan(CpmkVersi $versi): array
    {
        $versi->loadMissing(CpmkVersi::MUAT);

        if ($versi->cpmk->isEmpty()) {
            return [__('capaian.kekurangan.kosong')];
        }

        $pesan = [];
        $perCpl = [];

        foreach ($versi->cpmk as $cpmk) {
            if ($cpmk->cpl->isEmpty()) {
                $pesan[] = __('capaian.kekurangan.tanpa_cpl', ['kode' => $cpmk->kode]);
            }

            if ($cpmk->sub->isNotEmpty() && ($jumlah = (int) $cpmk->sub->sum('bobot')) !== 100) {
                $pesan[] = __('capaian.kekurangan.bobot_sub', ['kode' => $cpmk->kode, 'jumlah' => $jumlah]);
            }

            foreach ($cpmk->cpl as $cpl) {
                $perCpl[$cpl->kode] = ($perCpl[$cpl->kode] ?? 0) + (int) $cpl->pivot->bobot;
            }
        }

        ksort($perCpl);

        foreach ($perCpl as $kodeCpl => $jumlah) {
            if ($jumlah !== 100) {
                $pesan[] = __('capaian.kekurangan.bobot_cpl', ['kode' => $kodeCpl, 'jumlah' => $jumlah]);
            }
        }

        return $pesan;
    }

    /**
     * Puts a draft in force and archives the version it replaces.
     *
     * Classes already mapped to the old version keep it: their marks were
     * measured against those rows. A class not yet finalised is offered the
     * new version on its evaluation plan.
     *
     * @throws AturanAkademikException
     */
    public function terbitkan(CpmkVersi $versi, Staff|Dosen $oleh): CpmkVersi
    {
        $this->pastikanDraf($versi);

        // Fresh relations: the rows checked must be the rows being published,
        // not whatever an earlier read of this model left loaded.
        if (($kekurangan = $this->kekurangan($versi->load(CpmkVersi::MUAT))) !== []) {
            throw new AturanAkademikException(__('capaian.galat.belum_terbit').' '.implode(' ', $kekurangan));
        }

        return DB::transaction(function () use ($versi, $oleh): CpmkVersi {
            CpmkVersi::query()
                ->where('kurikulum_id', $versi->kurikulum_id)
                ->where('mata_kuliah_id', $versi->mata_kuliah_id)
                ->aktif()
                ->get()
                ->each(fn (CpmkVersi $lama) => $lama->update([
                    'status' => StatusCpmk::Diarsipkan,
                    'kunci_aktif' => null,
                ]));

            try {
                $versi->update([
                    'status' => StatusCpmk::Berlaku,
                    'kunci_aktif' => $versi->kurikulum_id.':'.$versi->mata_kuliah_id,
                    'diterbitkan_oleh' => $oleh->nama,
                    'diterbitkan_at' => now(),
                ]);
            } catch (UniqueConstraintViolationException) {
                throw new AturanAkademikException(__('capaian.galat.balapan'));
            }

            return $versi->fresh(CpmkVersi::MUAT);
        });
    }

    /**
     * The targets a grade component can measure, keyed "s:{id}" / "c:{id}".
     *
     * A sub-CPMK when its CPMK has any, otherwise the CPMK itself — never both
     * levels of one CPMK, so a mark is never counted twice on its way up.
     *
     * @return Collection<string, array{kode: string, rumusan: string, cpmk: Cpmk, sub: SubCpmk|null}>
     */
    public function sasaran(CpmkVersi $versi): Collection
    {
        $versi->loadMissing(CpmkVersi::MUAT);

        $hasil = collect();

        foreach ($versi->cpmk as $cpmk) {
            if ($cpmk->sub->isEmpty()) {
                $hasil->put('c:'.$cpmk->id, [
                    'kode' => $cpmk->kode,
                    'rumusan' => $cpmk->rumusan,
                    'cpmk' => $cpmk,
                    'sub' => null,
                ]);

                continue;
            }

            foreach ($cpmk->sub as $sub) {
                $hasil->put('s:'.$sub->id, [
                    'kode' => $sub->kode,
                    'rumusan' => $sub->rumusan,
                    'cpmk' => $cpmk,
                    'sub' => $sub,
                ]);
            }
        }

        return $hasil;
    }

    /**
     * The version a class's components are currently mapped to, if any.
     *
     * Read from the mapping itself: after a newer version is published, a
     * finalised class still answers with the version its marks belong to.
     */
    public function versiTerpetakan(KelasKuliah $kelas): ?CpmkVersi
    {
        $versiId = $this->idVersiTerpetakan($kelas);

        return $versiId === null ? null : CpmkVersi::find($versiId);
    }

    /** The id alone, in one query — for callers that only need to compare. */
    public function idVersiTerpetakan(KelasKuliah $kelas): ?int
    {
        $versiId = DB::table('komponen_nilai_cpmk')
            ->join('komponen_nilai', 'komponen_nilai.id', '=', 'komponen_nilai_cpmk.komponen_nilai_id')
            ->join('cpmk', 'cpmk.id', '=', 'komponen_nilai_cpmk.cpmk_id')
            ->where('komponen_nilai.kelas_kuliah_id', $kelas->id)
            ->value('cpmk.cpmk_versi_id');

        return $versiId === null ? null : (int) $versiId;
    }

    /**
     * The version a class should be measured against.
     *
     * A finalised class keeps the version it was measured with. Otherwise the
     * plan's version (the RPS is the one source of what the course teaches),
     * and failing that the version in force for the course.
     */
    public function versiUntukKelas(KelasKuliah $kelas): ?CpmkVersi
    {
        if ($kelas->status_nilai === 'final') {
            return $this->versiTerpetakan($kelas);
        }

        $rps = Rps::untuk($kelas->mata_kuliah_id, $kelas->tahun_akademik_id);

        if ($rps?->cpmk_versi_id !== null) {
            $versi = CpmkVersi::find($rps->cpmk_versi_id);

            if ($versi?->berlaku()) {
                return $versi;
            }
        }

        $kelas->loadMissing('mataKuliah');

        return $this->berlakuUntuk($kelas->mataKuliah);
    }

    /**
     * Every course of a curriculum with the state of its outcome set.
     *
     * Two queries for the whole list: the courses, and every version of the
     * curriculum with its CPMK count.
     *
     * @return Collection<int, array{mata_kuliah: MataKuliah, semester: int, berlaku: CpmkVersi|null, draf: CpmkVersi|null}>
     */
    public function ringkasKurikulum(Kurikulum $kurikulum): Collection
    {
        $versi = CpmkVersi::query()
            ->withCount('cpmk')
            ->where('kurikulum_id', $kurikulum->id)
            ->whereIn('status', [StatusCpmk::Berlaku->value, StatusCpmk::Draft->value])
            ->get()
            ->groupBy('mata_kuliah_id');

        return $kurikulum->mataKuliah()
            ->orderBy('kode')
            ->get()
            ->map(fn (MataKuliah $mk): array => [
                'mata_kuliah' => $mk,
                'semester' => (int) $mk->pivot->semester,
                'berlaku' => ($versi->get($mk->id) ?? collect())->first(fn (CpmkVersi $v): bool => $v->berlaku()),
                'draf' => ($versi->get($mk->id) ?? collect())->first(fn (CpmkVersi $v): bool => $v->status->dapatDisunting()),
            ]);
    }

    /**
     * Every version of one course in one curriculum, newest first.
     *
     * @return Collection<int, CpmkVersi>
     */
    public function riwayat(Kurikulum $kurikulum, MataKuliah $mataKuliah): Collection
    {
        return CpmkVersi::query()
            ->withCount('cpmk')
            ->where('kurikulum_id', $kurikulum->id)
            ->where('mata_kuliah_id', $mataKuliah->id)
            ->orderByDesc('versi')
            ->get();
    }

    /* ---------------------------------------------------------------------
     | Internals
     |-------------------------------------------------------------------- */

    private function tulis(CpmkVersi $versi, RumusanCpmkData $data): void
    {
        foreach ($data->cpmk as $urutan => $baris) {
            $cpmk = Cpmk::create([
                'cpmk_versi_id' => $versi->id,
                'kode' => $baris->kode,
                'rumusan' => $baris->rumusan,
                'urutan' => $urutan,
            ]);

            foreach ($baris->sub as $u => $sub) {
                SubCpmk::create([
                    'cpmk_id' => $cpmk->id,
                    'kode' => $sub->kode,
                    'rumusan' => $sub->rumusan,
                    'bobot' => $sub->bobot,
                    'urutan' => $u,
                ]);
            }

            $cpmk->cpl()->sync(collect($baris->cpl)
                ->mapWithKeys(fn (int $bobot, int $cplId): array => [$cplId => ['bobot' => $bobot]])
                ->all());
        }
    }

    private function sebagaiData(CpmkVersi $versi): RumusanCpmkData
    {
        return new RumusanCpmkData($versi->cpmk->map(fn (Cpmk $c): CpmkData => new CpmkData(
            kode: $c->kode,
            rumusan: $c->rumusan,
            cpl: $c->cpl->mapWithKeys(fn (ProdiCpl $p): array => [$p->id => (int) $p->pivot->bobot])->all(),
            sub: $c->sub->map(fn (SubCpmk $s): SubCpmkData => new SubCpmkData(
                kode: $s->kode,
                rumusan: $s->rumusan,
                bobot: $s->bobot,
            ))->values()->all(),
        ))->values()->all());
    }

    private function pastikanDraf(CpmkVersi $versi): void
    {
        if (!$versi->status->dapatDisunting()) {
            throw new AturanAkademikException(__('capaian.galat.sudah_berlaku'));
        }
    }
}

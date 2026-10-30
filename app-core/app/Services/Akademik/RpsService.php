<?php

declare(strict_types=1);

namespace App\Services\Akademik;

use App\Enums\StatusRps;
use App\Exceptions\AturanAkademikException;
use App\Models\Akademik\CpmkVersi;
use App\Models\Akademik\KelasKuliah;
use App\Models\Akademik\MataKuliah;
use App\Models\Akademik\ProdiCpl;
use App\Models\Akademik\Rps;
use App\Models\Akademik\RpsPertemuan;
use App\Models\Akademik\SubCpmk;
use App\Models\Akademik\TahunAkademik;
use App\Models\Sdm\Dosen;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Writing and publishing a teaching plan.
 *
 * Two rules carry the module, and both exist so the mastery figures downstream
 * mean something:
 *
 *  1. **A published plan is frozen.** A mark recorded in week four against
 *     CPL-02 must still belong to CPL-02 in week twelve. Revising a plan in
 *     force means publishing a new version, not editing the one students are
 *     being assessed against.
 *
 *  2. **Every outcome the course claims must be measured by something.** A plan
 *     that lists five CPL and assesses two produces three outcomes that appear
 *     to have been taught and can never be reported on — the exact gap an
 *     accreditation visit looks for.
 */
class RpsService
{
    public function __construct(private readonly CpmkService $cpmk) {}

    /** Starts a draft, or returns the draft already in progress. */
    public function mulai(
        MataKuliah $mataKuliah,
        TahunAkademik $term,
        ?Dosen $penyusun = null,
    ): Rps {
        $draft = Rps::query()
            ->where('mata_kuliah_id', $mataKuliah->id)
            ->where('tahun_akademik_id', $term->id)
            ->where('status', StatusRps::Draft->value)
            ->first();

        $versiCpmk = $this->cpmk->berlakuUntuk($mataKuliah);

        if ($draft !== null) {
            /*
             * A draft follows the CPMK version in force.
             *
             * The programme may publish a revised set while a lecturer is half
             * way through a plan; the draft is still editable, so it moves to
             * the new set rather than being published against an archived one.
             */
            if ($versiCpmk !== null && $draft->cpmk_versi_id !== $versiCpmk->id) {
                $this->pasangCpmk($draft, $versiCpmk);
            }

            return $draft;
        }

        $berlaku = Rps::untuk($mataKuliah->id, $term->id);

        return DB::transaction(function () use ($mataKuliah, $term, $penyusun, $berlaku, $versiCpmk): Rps {
            $rps = Rps::create([
                'mata_kuliah_id' => $mataKuliah->id,
                'tahun_akademik_id' => $term->id,
                'versi' => ($berlaku?->versi ?? 0) + 1,
                'status' => StatusRps::Draft,
                'disusun_by_dosen_id' => $penyusun?->id,
            ]);

            /*
             * A revision starts from the plan in force rather than from nothing.
             *
             * Retyping sixteen weeks to fix one of them is how a campus ends up
             * never revising anything.
             */
            if ($berlaku !== null) {
                $this->salinDari($berlaku, $rps);
            }

            if ($versiCpmk !== null) {
                $this->pasangCpmk($rps, $versiCpmk);
            }

            return $rps->fresh(['pertemuan', 'cpl']);
        });
    }

    /**
     * Publishes, and takes the previous version out of force.
     *
     * @throws AturanAkademikException when the plan is not internally complete
     */
    public function terbitkan(Rps $rps, ?Dosen $pengesah = null): Rps
    {
        if (!$rps->status->dapatDisunting()) {
            throw new AturanAkademikException('Hanya draf yang dapat diterbitkan.');
        }

        if ($rps->cpmk_versi_id !== null) {
            $this->selaraskanCpl($rps);
            $rps->unsetRelation('cpl');
        }

        $this->pastikanLengkap($rps);

        return DB::transaction(function () use ($rps, $pengesah): Rps {
            // The one in force steps down first. The unique key would refuse the
            // second claim otherwise, and that refusal would be correct but
            // unhelpful.
            Rps::query()
                ->where('mata_kuliah_id', $rps->mata_kuliah_id)
                ->where('tahun_akademik_id', $rps->tahun_akademik_id)
                ->aktif()
                ->update([
                    'status' => StatusRps::Diarsipkan->value,
                    'kunci_aktif' => null,
                ]);

            try {
                $rps->update([
                    'status' => StatusRps::Berlaku,
                    'kunci_aktif' => $rps->mata_kuliah_id.':'.$rps->tahun_akademik_id,
                    'disahkan_by_dosen_id' => $pengesah?->id,
                    'disahkan_at' => now(),
                ]);
            } catch (UniqueConstraintViolationException) {
                throw new AturanAkademikException(
                    'RPS mata kuliah ini baru saja diterbitkan orang lain. Muat ulang halaman.',
                );
            }

            return $rps->fresh(['pertemuan', 'cpl']);
        });
    }

    /**
     * Description and references. Frozen with the rest once published: this
     * used to be written straight from the controller, ahead of the draft
     * check in simpanPertemuan(), so a published RPS refused the save with an
     * error and had its text changed anyway.
     */
    public function simpanIsi(Rps $rps, ?string $deskripsi, ?string $pustaka): void
    {
        $this->pastikanDraft($rps);

        $rps->update(['deskripsi' => $deskripsi, 'pustaka' => $pustaka]);
    }

    /**
     * Replaces the whole weekly plan in one go.
     *
     * A week may name the sub-CPMK it teaches, from the plan's own CPMK
     * version — the same rows the grade components measure.
     */
    public function simpanPertemuan(Rps $rps, array $baris): void
    {
        $this->pastikanDraft($rps);

        $subSah = $rps->cpmk_versi_id === null
            ? collect()
            : SubCpmk::query()
                ->whereHas('cpmk', fn ($q) => $q->where('cpmk_versi_id', $rps->cpmk_versi_id))
                ->pluck('id')
                ->flip();

        foreach ($baris as $satu) {
            if (filled($satu['sub_cpmk_id'] ?? null) && !$subSah->has((int) $satu['sub_cpmk_id'])) {
                throw new AturanAkademikException(__('capaian.galat.sub_bukan_versi_rps'));
            }
        }

        DB::transaction(function () use ($rps, $baris): void {
            $rps->pertemuan()->delete();

            foreach ($baris as $satu) {
                RpsPertemuan::create([
                    'rps_id' => $rps->id,
                    'pertemuan_ke' => (int) $satu['pertemuan_ke'],
                    'sub_cpmk_id' => filled($satu['sub_cpmk_id'] ?? null) ? (int) $satu['sub_cpmk_id'] : null,
                    'kemampuan_akhir' => $satu['kemampuan_akhir'],
                    'bahan_kajian' => $satu['bahan_kajian'] ?? null,
                    'metode' => $satu['metode'] ?? null,
                    'indikator' => $satu['indikator'] ?? null,
                    'bobot' => (int) ($satu['bobot'] ?? 0),
                ]);
            }
        });
    }

    /**
     * Sets which programme outcomes this course answers for.
     *
     * @param array<int, array{prodi_cpl_id: int, rumusan?: ?string}> $cpl
     */
    public function simpanCpl(Rps $rps, array $cpl): void
    {
        $this->pastikanDraft($rps);

        if ($rps->cpmk_versi_id !== null) {
            // One source: a plan attached to a CPMK version answers for the
            // CPL its CPMK feed, and nothing typed here can say otherwise.
            throw new AturanAkademikException(__('capaian.galat.cpl_dari_cpmk'));
        }

        $prodiId = $rps->mataKuliah->prodi_id;

        $sah = ProdiCpl::where('prodi_id', $prodiId)->pluck('id')->flip();

        foreach ($cpl as $satu) {
            if (!$sah->has((int) $satu['prodi_cpl_id'])) {
                // Borrowing another programme's outcome would put a figure on a
                // curriculum map where it does not belong, and nobody reading
                // the map afterwards could tell.
                throw new AturanAkademikException(
                    'CPL yang dipilih bukan milik program studi mata kuliah ini.',
                );
            }
        }

        $rps->cpl()->sync(
            collect($cpl)->mapWithKeys(fn (array $s): array => [
                (int) $s['prodi_cpl_id'] => ['rumusan' => $s['rumusan'] ?? null],
            ])->all(),
        );
    }

    /**
     * Outcomes claimed by the plan that nothing in the class actually measures.
     *
     * Reported per class rather than per plan, because the components live on
     * the class: two parallel classes teaching one plan can differ in whether
     * they wired their midterm to an outcome.
     *
     * @return Collection<int, ProdiCpl>
     */
    public function cplTanpaPengukur(Rps $rps, KelasKuliah $kelas): Collection
    {
        $diukur = DB::table('komponen_nilai_cpl')
            ->join('komponen_nilai', 'komponen_nilai.id', '=', 'komponen_nilai_cpl.komponen_nilai_id')
            ->where('komponen_nilai.kelas_kuliah_id', $kelas->id)
            ->pluck('komponen_nilai_cpl.prodi_cpl_id')

            // ...and through the CPMK a component measures, which is how every
            // mapping since ADR-038 reaches a CPL.
            ->merge(DB::table('komponen_nilai_cpmk')
                ->join('komponen_nilai', 'komponen_nilai.id', '=', 'komponen_nilai_cpmk.komponen_nilai_id')
                ->join('cpmk_cpl', 'cpmk_cpl.cpmk_id', '=', 'komponen_nilai_cpmk.cpmk_id')
                ->where('komponen_nilai.kelas_kuliah_id', $kelas->id)
                ->pluck('cpmk_cpl.prodi_cpl_id'))
            ->unique()
            ->flip();

        return $rps->cpl->reject(fn (ProdiCpl $c): bool => $diukur->has($c->id))->values();
    }

    /**
     * Everything that stops this plan being publishable.
     *
     * Returns sentences rather than a boolean: "tidak lengkap" without saying
     * which week or which outcome sends somebody scrolling through sixteen rows.
     *
     * @return array<int, string>
     */
    public function kekurangan(Rps $rps): array
    {
        $pesan = [];

        $jumlah = (int) config('academic.attendance.meetings_per_term', 16);

        if ($rps->pertemuan->count() < $jumlah) {
            $pesan[] = sprintf(
                'Baru %d dari %d pertemuan yang terisi.',
                $rps->pertemuan->count(),
                $jumlah,
            );
        }

        $kosong = $rps->pertemuan
            ->filter(fn (RpsPertemuan $p): bool => blank($p->kemampuan_akhir))
            ->pluck('pertemuan_ke');

        if ($kosong->isNotEmpty()) {
            $pesan[] = 'Kemampuan akhir belum diisi pada pertemuan '.$kosong->implode(', ').'.';
        }

        $bobot = $rps->totalBobot();

        if ($bobot !== 100) {
            $pesan[] = sprintf('Bobot penilaian berjumlah %d%%, seharusnya 100%%.', $bobot);
        }

        if ($rps->cpl->isEmpty()) {
            $pesan[] = 'Belum ada CPL yang dibebankan pada mata kuliah ini.';
        }

        if ($rps->cpmk_versi_id !== null && !(CpmkVersi::find($rps->cpmk_versi_id)?->berlaku() ?? false)) {
            $pesan[] = __('capaian.kekurangan.rps_versi_usang');
        }

        return $pesan;
    }

    private function pastikanLengkap(Rps $rps): void
    {
        $rps->loadMissing(['pertemuan', 'cpl']);

        $kekurangan = $this->kekurangan($rps);

        if ($kekurangan !== []) {
            throw new AturanAkademikException(
                'RPS belum dapat diterbitkan. '.implode(' ', $kekurangan),
            );
        }
    }

    private function pastikanDraft(Rps $rps): void
    {
        if (!$rps->status->dapatDisunting()) {
            throw new AturanAkademikException(
                'RPS yang sudah berlaku tidak dapat disunting. Buat versi baru bila perlu direvisi — '
                    .'nilai yang sudah diukur terhadap rumusan lama tidak boleh berubah artinya.',
            );
        }
    }

    private function salinDari(Rps $dari, Rps $ke): void
    {
        $dari->loadMissing(['pertemuan', 'cpl']);

        foreach ($dari->pertemuan as $p) {
            RpsPertemuan::create([
                'rps_id' => $ke->id,
                'pertemuan_ke' => $p->pertemuan_ke,
                // Carried over while the plans share a CPMK version;
                // pasangCpmk() clears it when the new draft moves to another.
                'sub_cpmk_id' => $p->sub_cpmk_id,
                'kemampuan_akhir' => $p->kemampuan_akhir,
                'bahan_kajian' => $p->bahan_kajian,
                'metode' => $p->metode,
                'indikator' => $p->indikator,
                'bobot' => $p->bobot,
            ]);
        }

        $ke->cpl()->sync(
            $dari->cpl->mapWithKeys(fn (ProdiCpl $c): array => [
                $c->id => ['rumusan' => $c->pivot->rumusan],
            ])->all(),
        );

        $ke->update([
            'deskripsi' => $dari->deskripsi,
            'pustaka' => $dari->pustaka,
            'cpmk_versi_id' => $dari->cpmk_versi_id,
        ]);
    }

    /**
     * Attaches a draft plan to a CPMK version and derives its CPL from it.
     *
     * Weeks that named a sub-CPMK of another version lose the link; the text
     * of the week stays, so nothing the lecturer wrote disappears.
     */
    private function pasangCpmk(Rps $rps, CpmkVersi $versi): void
    {
        DB::transaction(function () use ($rps, $versi): void {
            $rps->update(['cpmk_versi_id' => $versi->id]);

            $subSah = SubCpmk::query()
                ->whereHas('cpmk', fn ($q) => $q->where('cpmk_versi_id', $versi->id))
                ->pluck('id');

            $rps->pertemuan()
                ->whereNotNull('sub_cpmk_id')
                ->whereNotIn('sub_cpmk_id', $subSah)
                ->update(['sub_cpmk_id' => null]);

            $this->selaraskanCpl($rps);
        });
    }

    /**
     * rps_cpl as a reflection of the attached version.
     *
     * Kept written rather than dropped, because the plan screens and plans
     * written before ADR-038 read rps_cpl. The CPMK text per CPL is the
     * version's own wording.
     */
    private function selaraskanCpl(Rps $rps): void
    {
        $versi = CpmkVersi::with(CpmkVersi::MUAT)->find($rps->cpmk_versi_id);

        if ($versi === null) {
            return;
        }

        $perCpl = [];

        foreach ($versi->cpmk as $cpmk) {
            foreach ($cpmk->cpl as $cpl) {
                $perCpl[$cpl->id][] = $cpmk->kode.' '.$cpmk->rumusan;
            }
        }

        $rps->cpl()->sync(collect($perCpl)
            ->map(fn (array $rumusan): array => ['rumusan' => implode("\n", $rumusan)])
            ->all());
    }
}

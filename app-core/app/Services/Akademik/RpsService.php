<?php

declare(strict_types=1);

namespace App\Services\Akademik;

use App\Enums\StatusRps;
use App\Exceptions\AturanAkademikException;
use App\Models\Akademik\KelasKuliah;
use App\Models\Akademik\MataKuliah;
use App\Models\Akademik\ProdiCpl;
use App\Models\Akademik\Rps;
use App\Models\Akademik\RpsPertemuan;
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

        if ($draft !== null) {
            return $draft;
        }

        $berlaku = Rps::untuk($mataKuliah->id, $term->id);

        return DB::transaction(function () use ($mataKuliah, $term, $penyusun, $berlaku): Rps {
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

    /** Replaces the whole weekly plan in one go. */
    public function simpanPertemuan(Rps $rps, array $baris): void
    {
        $this->pastikanDraft($rps);

        DB::transaction(function () use ($rps, $baris): void {
            $rps->pertemuan()->delete();

            foreach ($baris as $satu) {
                RpsPertemuan::create([
                    'rps_id' => $rps->id,
                    'pertemuan_ke' => (int) $satu['pertemuan_ke'],
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
        ]);
    }
}

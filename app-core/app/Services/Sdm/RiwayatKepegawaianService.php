<?php

declare(strict_types=1);

namespace App\Services\Sdm;

use App\DTOs\Sdm\HasilValidasiRiwayat;
use App\DTOs\Sdm\RiwayatKepegawaianData;
use App\Enums\JenisRiwayat;
use App\Enums\StatusRiwayat;
use App\Exceptions\AturanAkademikException;
use App\Models\Sdm\BahasaDosen;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\MutasiDosen;
use App\Models\Sdm\PangkatDosen;
use App\Models\Sdm\PensiunDosen;
use App\Models\Sdm\Staff;
use App\Models\Sdm\UnitKerja;
use App\Services\Berkas\BerkasService;
use App\Support\Sdm\FormulirRiwayat;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The personnel file beyond the account: fourteen typed histories and the
 * review that stands between a lecturer's entry and the campus vouching for it.
 *
 * Distinct from KepegawaianService, which creates accounts and disables them.
 *
 * Two paths in, one state out. A lecturer *submits* from their portal and the
 * row waits; a staff member *records* and the row is approved at once, because
 * the staff member is the reviewer. Either way the effects of approval — the
 * current rank moving, a retirement closing an account — happen at the moment
 * of approval and only then, so nothing reads an unchecked claim as fact.
 *
 * Most histories are flat and their approval does nothing else. Three carry a
 * rule, and the rules live here:
 *
 *  - **rank** keeps exactly one current row, the approved one with the latest TMT;
 *  - **posting** moves `dosen.unit_kerja_id` with it (staff only);
 *  - **retirement** closes the account once its TMT arrives (staff only).
 *
 * Study leave carries the fourth, but it is read rather than applied: see
 * BkdService::wajibMelapor() and Dosen::sedangTugasBelajar().
 */
class RiwayatKepegawaianService
{
    public function __construct(
        private readonly BerkasService $berkas,
        private readonly KepegawaianService $kepegawaian,
    ) {}

    /* ---------------------------------------------------------------------
     | Filing and reviewing
     |-------------------------------------------------------------------- */

    /**
     * A lecturer's own entry, waiting for review.
     *
     * Never approved on creation, whoever is signed in — the same rule as a
     * self-reported BKD activity. An entry that arrives pre-approved is a
     * SISTER record resting on a claim nobody checked.
     */
    public function ajukan(Dosen $dosen, RiwayatKepegawaianData $data): Model
    {
        if (!$data->jenis->diisiDosen()) {
            throw new AturanAkademikException(
                __('kepegawaian.pesan.bukan_isian_dosen', ['jenis' => $data->jenis->label()]),
            );
        }

        return DB::transaction(fn (): Model => $this->tulisBaru($dosen, $data, [
            'status' => StatusRiwayat::Diajukan,
        ]));
    }

    /**
     * A staff member's entry, approved as it is written.
     *
     * The staff member is the reviewer, so a separate approval step would be
     * one person agreeing with themselves. The approval's effects still run.
     */
    public function catat(Dosen $dosen, Staff $staf, RiwayatKepegawaianData $data): HasilValidasiRiwayat
    {
        return DB::transaction(function () use ($dosen, $staf, $data): HasilValidasiRiwayat {
            $persetujuan = [
                'status' => StatusRiwayat::Disetujui,
                'divalidasi_oleh' => $staf->id,
                'divalidasi_at' => now(),
            ];

            if ($data->jenis === JenisRiwayat::Mutasi) {
                return new HasilValidasiRiwayat($this->catatMutasi($dosen, [
                    ...$data->atribut,
                    ...$persetujuan,
                    'dokumen_path' => $this->simpanDokumen($dosen, $data),
                ]));
            }

            $baris = $this->tulisBaru($dosen, $data, $persetujuan);

            return new HasilValidasiRiwayat($baris, $this->terapkan($baris));
        });
    }

    /**
     * Changes an entry.
     *
     * From the portal, only while it is not yet approved — and an edit sends it
     * back to the queue, since what was reviewed is no longer what is there. A
     * staff member may correct an approved row; the correction keeps its
     * approval and re-runs whatever the approval set in motion.
     */
    public function ubah(Model $baris, RiwayatKepegawaianData $data, ?Staff $staf = null): HasilValidasiRiwayat
    {
        if ($baris instanceof MutasiDosen) {
            // A posting already moved somebody. Correcting it means recording
            // the move that should have happened, which keeps both visible.
            throw new AturanAkademikException('Mutasi yang sudah tercatat dikoreksi dengan mencatat mutasi baru.');
        }

        if ($staf === null && !$baris->status->dapatDisunting()) {
            throw new AturanAkademikException(__('kepegawaian.pesan.sudah_disetujui'));
        }

        return DB::transaction(function () use ($baris, $data, $staf): HasilValidasiRiwayat {
            $this->tolakBahasaGanda($baris->dosen, $data, $baris);

            $perubahan = $data->atribut;

            if ($data->dokumen !== null) {
                $lama = $baris->dokumen_path;
                $perubahan['dokumen_path'] = $this->simpanDokumen($baris->dosen, $data);

                // Only once the new path is committed — a rollback must not
                // leave the row pointing at a file that is gone.
                DB::afterCommit(fn () => $this->berkas->hapus($lama));
            }

            if ($staf === null) {
                $perubahan += ['status' => StatusRiwayat::Diajukan, 'alasan_tolak' => null];
            }

            $baris->update($perubahan);

            return new HasilValidasiRiwayat($baris, $baris->sudahDisetujui() ? $this->terapkan($baris) : []);
        });
    }

    /** Approves a waiting entry and applies whatever approval means for it. */
    public function setujui(Model $baris, Staff $staf): HasilValidasiRiwayat
    {
        $this->pastikanMenunggu($baris);

        return DB::transaction(function () use ($baris, $staf): HasilValidasiRiwayat {
            $baris->update([
                'status' => StatusRiwayat::Disetujui,
                'alasan_tolak' => null,
                'divalidasi_oleh' => $staf->id,
                'divalidasi_at' => now(),
            ]);

            return new HasilValidasiRiwayat($baris, $this->terapkan($baris));
        });
    }

    /**
     * Returns an entry to its lecturer, with the reason.
     *
     * The reason is required for the same reason a BKD assessor's note is: it
     * is the only thing the lecturer can act on.
     */
    public function tolak(Model $baris, Staff $staf, string $alasan): Model
    {
        $this->pastikanMenunggu($baris);

        if (mb_strlen(trim($alasan)) < 5) {
            throw new AturanAkademikException(__('kepegawaian.pesan.alasan_wajib'));
        }

        $baris->update([
            'status' => StatusRiwayat::Ditolak,
            'alasan_tolak' => trim($alasan),
            'divalidasi_oleh' => $staf->id,
            'divalidasi_at' => now(),
        ]);

        return $baris;
    }

    /**
     * Removes an entry — soft, and with an audit row, like every history.
     *
     * Removing the current rank hands "current" to the next approved rank by
     * TMT rather than leaving the lecturer with none.
     */
    public function hapus(Model $baris): void
    {
        if ($baris instanceof MutasiDosen) {
            throw new AturanAkademikException('Mutasi yang sudah tercatat dikoreksi dengan mencatat mutasi baru.');
        }

        DB::transaction(function () use ($baris): void {
            if ($baris instanceof PangkatDosen && $baris->berlaku()) {
                $baris->update(['dosen_aktif_id' => null]);
                $baris->delete();
                $this->segarkanPangkatBerlaku($baris->dosen);

                return;
            }

            $baris->delete();
        });
    }

    /**
     * Every entry waiting for review, oldest first, across all fourteen.
     *
     * One query per history. Fourteen small queries beat a UNION across
     * tables that share no columns beyond the review ones.
     *
     * @return Collection<int, Model>
     */
    public function antrean(?JenisRiwayat $jenis = null): Collection
    {
        return collect($jenis === null ? JenisRiwayat::cases() : [$jenis])
            ->flatMap(fn (JenisRiwayat $j): Collection => $j->model()::query()
                ->menungguValidasi()
                ->with(['dosen.prodi', ...FormulirRiwayat::relasiRingkasan($j)])
                ->get())
            ->sortBy('created_at')
            ->values();
    }

    /** How many entries are waiting, for a badge — one COUNT per history. */
    public function jumlahMenunggu(): int
    {
        return collect(JenisRiwayat::cases())
            ->sum(fn (JenisRiwayat $j): int => $j->model()::query()->menungguValidasi()->count());
    }

    /**
     * One lecturer's histories, keyed by JenisRiwayat value, newest first.
     *
     * @return array<string, Collection<int, Model>>
     */
    public function riwayat(Dosen $dosen): array
    {
        $dosen->loadMissing(collect(JenisRiwayat::cases())
            ->mapWithKeys(fn (JenisRiwayat $j): array => [
                $j->relasi() => fn ($q) => $q->with(FormulirRiwayat::relasiRingkasan($j)),
            ])
            ->all());

        $riwayat = collect(JenisRiwayat::cases())
            ->mapWithKeys(fn (JenisRiwayat $j): array => [
                $j->value => $dosen->{$j->relasi()}
                    ->sortByDesc(fn (Model $m) => $m->getAttribute($j->kolomUrut()))
                    ->values(),
            ]);

        /*
         * Validators in one query for all fourteen.
         *
         * Eager-loading `validator` per relation costs fourteen queries to
         * name the same two or three staff members; the file screen is the
         * one that opens every relation at once.
         */
        $staf = Staff::query()
            ->whereKey($riwayat->flatten()->pluck('divalidasi_oleh')->filter()->unique()->values())
            ->get()
            ->keyBy('id');

        $riwayat->flatten()->each(fn (Model $m) => $m->setRelation('validator', $staf->get($m->divalidasi_oleh)));

        return $riwayat->all();
    }

    /* ---------------------------------------------------------------------
     | Rank
     |-------------------------------------------------------------------- */

    /**
     * Promotes a lecturer, retiring whichever rank was current.
     *
     * Both writes in one transaction. The nullable-unique column means the
     * database refuses two current ranks outright, so a half-finished promotion
     * does not leave the lecturer with none — it leaves the promotion undone,
     * which is recoverable and visible.
     *
     * A promotion recorded this way is an employer's act, so it is approved as
     * it is written.
     *
     * @param array<string, mixed> $data
     */
    public function naikPangkat(Dosen $dosen, array $data): PangkatDosen
    {
        return DB::transaction(function () use ($dosen, $data): PangkatDosen {
            PangkatDosen::where('dosen_aktif_id', $dosen->id)
                ->update(['dosen_aktif_id' => null]);

            return PangkatDosen::create([
                'status' => StatusRiwayat::Disetujui,
                ...$data,
                'dosen_id' => $dosen->id,
                'dosen_aktif_id' => $dosen->id,
            ]);
        });
    }

    /** The rank in force, or null for a lecturer who has never been ranked. */
    public function pangkatBerlaku(Dosen $dosen): ?PangkatDosen
    {
        return PangkatDosen::where('dosen_aktif_id', $dosen->id)->first();
    }

    /**
     * Points "current" at the approved rank with the latest TMT.
     *
     * Not "the most recently approved": a lecturer entering twenty years of
     * history files the old decrees last, and approving them must not demote
     * anybody. Released before it is claimed, so the unique column never sees
     * two.
     */
    private function segarkanPangkatBerlaku(Dosen $dosen): void
    {
        $terbaru = PangkatDosen::query()
            ->where('dosen_id', $dosen->id)
            ->disetujui()
            ->orderByDesc('tmt')
            ->orderByDesc('id')
            ->first();

        PangkatDosen::where('dosen_aktif_id', $dosen->id)
            ->when($terbaru, fn ($q) => $q->whereKeyNot($terbaru->id))
            ->update(['dosen_aktif_id' => null]);

        $terbaru?->update(['dosen_aktif_id' => $dosen->id]);
    }

    /* ---------------------------------------------------------------------
     | Posting
     |-------------------------------------------------------------------- */

    /**
     * Records a posting and moves the lecturer's current unit with it.
     *
     * The pointer and the history are written together, deliberately. Deriving
     * "current unit" from the latest mutation row would be one fewer column and
     * one more way to be wrong: a back-dated correction would silently move
     * somebody who never moved, and every screen would pay a subquery to ask
     * where a person works.
     *
     * @param array<string, mixed> $data
     */
    public function catatMutasi(Dosen $dosen, array $data): MutasiDosen
    {
        $tujuan = $data['unit_tujuan_id'] ?? null;

        if ($tujuan !== null) {
            $unit = UnitKerja::find($tujuan);

            if ($unit === null || !$unit->is_active) {
                throw new AturanAkademikException(
                    'Unit tujuan tidak ditemukan atau sudah tidak aktif.',
                );
            }
        }

        if (($data['jenis'] ?? null) === 'pindah' && $tujuan === null) {
            throw new AturanAkademikException('Mutasi pindah harus menyebutkan unit tujuan.');
        }

        return DB::transaction(function () use ($dosen, $data, $tujuan): MutasiDosen {
            $mutasi = MutasiDosen::create([
                // An employer's act, approved as it is written.
                'status' => StatusRiwayat::Disetujui,

                ...$data,

                // Filled from the pointer rather than trusted from the form:
                // the origin of a move is wherever the person actually is, and
                // a form can be submitted after somebody else already moved them.
                'unit_asal_id' => $data['unit_asal_id'] ?? $dosen->unit_kerja_id,

                'dosen_id' => $dosen->id,
            ]);

            /*
             * A departure clears the unit rather than leaving the last one.
             *
             * Somebody who has left is not still filed under their old bureau —
             * that is how a head count keeps counting people who resigned.
             */
            $dosen->update([
                'unit_kerja_id' => $data['jenis'] === 'keluar' ? null : $tujuan,
            ]);

            return $mutasi;
        });
    }

    /* ---------------------------------------------------------------------
     | Retirement
     |-------------------------------------------------------------------- */

    /**
     * Closes the accounts of lecturers whose approved retirement has arrived.
     *
     * Through KepegawaianService's own refusal, not around it: a retired
     * lecturer who still holds this term's class or a cohort of advisees is
     * left open and reported, because closing the account would strand those
     * students exactly as a careless click would. The scheduled run repeats
     * daily, so the account closes the day the hand-over is done.
     *
     * @return array{ditutup: Collection<int, Dosen>, tertahan: Collection<int, array{dosen: Dosen, halangan: array<int, string>}>}
     */
    public function tutupAkunPensiun(?CarbonInterface $hari = null, ?Dosen $hanya = null): array
    {
        $hari ??= now();

        $dosen = Dosen::query()
            ->aktif()
            ->when($hanya, fn ($q) => $q->whereKey($hanya->id))
            ->whereHas('pensiun', fn ($q) => $q->disetujui()->where('tmt', '<=', $hari->toDateString()))
            ->get();

        $ditutup = collect();
        $tertahan = collect();

        foreach ($dosen as $satu) {
            $halangan = $this->kepegawaian->halanganNonaktifDosen($satu);

            if ($halangan === []) {
                $ditutup->push($this->kepegawaian->nonaktifkanDosen($satu));
            } else {
                $tertahan->push(['dosen' => $satu, 'halangan' => $halangan]);
            }
        }

        return ['ditutup' => $ditutup, 'tertahan' => $tertahan];
    }

    /* ---------------------------------------------------------------------
     | The file as a whole
     |-------------------------------------------------------------------- */

    /**
     * Everything on one lecturer's personnel file, in one place.
     *
     * @return array<string, mixed>
     */
    public function berkas(Dosen $dosen): array
    {
        $dosen->loadMissing([
            'unitKerja',
            'keluarga',
            'pangkat',
            'mutasi.unitAsal',
            'mutasi.unitTujuan',
            'penghargaanSanksi',
            'bahasa',
            'organisasi',
        ]);

        return [
            'unit' => $dosen->unitKerja,
            'pangkatBerlaku' => $dosen->pangkat->firstWhere('dosen_aktif_id', $dosen->id),
            'pangkat' => $dosen->pangkat->sortByDesc('tmt')->values(),
            'keluarga' => $dosen->keluarga,
            'mutasi' => $dosen->mutasi->sortByDesc('tmt')->values(),

            /*
             * Two lists, not one balance.
             *
             * They share a table because they share every column, and nothing
             * anywhere adds them: an award does not offset a reprimand, and a
             * combined figure would be a judgement the campus never made.
             */
            'penghargaan' => $dosen->penghargaanSanksi->where('jenis', 'penghargaan')->values(),
            'sanksi' => $dosen->penghargaanSanksi->where('jenis', 'sanksi')->values(),

            'bahasa' => $dosen->bahasa,
            'organisasi' => $dosen->organisasi->sortByDesc('tahun_mulai')->values(),
        ];
    }

    /* ---------------------------------------------------------------------
     | Internals
     |-------------------------------------------------------------------- */

    /** @param array<string, mixed> $status */
    private function tulisBaru(Dosen $dosen, RiwayatKepegawaianData $data, array $status): Model
    {
        $this->tolakBahasaGanda($dosen, $data);

        $atribut = [
            ...$data->atribut,
            ...$status,
            'dosen_id' => $dosen->id,
            'dokumen_path' => $this->simpanDokumen($dosen, $data),
        ];

        /*
         * A language once removed comes back as the same row.
         *
         * bahasa_dosen is unique per lecturer per language, soft-deleted rows
         * included, so re-adding one would hit the index. Restoring keeps the
         * audit trail on one subject instead of failing on a constraint the
         * lecturer cannot see.
         */
        if ($data->jenis === JenisRiwayat::Bahasa) {
            $terhapus = BahasaDosen::onlyTrashed()
                ->where('dosen_id', $dosen->id)
                ->where('bahasa', $data->atribut['bahasa'])
                ->first();

            if ($terhapus !== null) {
                $terhapus->restore();
                $terhapus->update($atribut);

                return $terhapus;
            }
        }

        return $data->jenis->model()::create($atribut);
    }

    private function tolakBahasaGanda(Dosen $dosen, RiwayatKepegawaianData $data, ?Model $kecuali = null): void
    {
        if ($data->jenis !== JenisRiwayat::Bahasa || !isset($data->atribut['bahasa'])) {
            return;
        }

        $ada = BahasaDosen::query()
            ->where('dosen_id', $dosen->id)
            ->where('bahasa', $data->atribut['bahasa'])
            ->when($kecuali, fn ($q) => $q->whereKeyNot($kecuali->getKey()))
            ->exists();

        if ($ada) {
            throw new AturanAkademikException(__('kepegawaian.pesan.bahasa_ganda'));
        }
    }

    private function simpanDokumen(Dosen $dosen, RiwayatKepegawaianData $data): ?string
    {
        if ($data->dokumen === null) {
            return null;
        }

        return $this->berkas->simpan($data->dokumen, 'kepegawaian/'.$dosen->uuid);
    }

    private function pastikanMenunggu(Model $baris): void
    {
        if ($baris->status !== StatusRiwayat::Diajukan) {
            throw new AturanAkademikException(__('kepegawaian.pesan.sudah_divalidasi'));
        }
    }

    /**
     * What approval means for this row, beyond its status.
     *
     * @return array<int, string> sentences for the staff member
     */
    private function terapkan(Model $baris): array
    {
        if ($baris instanceof PangkatDosen) {
            $this->segarkanPangkatBerlaku($baris->dosen);

            return [];
        }

        if ($baris instanceof PensiunDosen && $baris->tmt->lte(now())) {
            $hasil = $this->tutupAkunPensiun(now(), $baris->dosen);

            return [
                ...$hasil['ditutup']->map(fn (Dosen $d): string => __('kepegawaian.pesan.akun_ditutup', ['nama' => $d->nama]))->all(),
                ...$hasil['tertahan']->map(fn (array $t): string => __('kepegawaian.pesan.akun_tertahan', [
                    'nama' => $t['dosen']->nama,
                    'halangan' => implode('; ', $t['halangan']),
                ]))->all(),
            ];
        }

        return [];
    }
}

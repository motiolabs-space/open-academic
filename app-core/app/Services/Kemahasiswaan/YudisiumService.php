<?php

declare(strict_types=1);

namespace App\Services\Kemahasiswaan;

use App\DTOs\Akademik\PerolehanBaris;
use App\DTOs\Kemahasiswaan\SyaratKelulusan;
use App\Enums\GradeLetter;
use App\Enums\JenisPoin;
use App\Enums\StudentStatus;
use App\Enums\TugasAkhirStatus;
use App\Exceptions\AturanAkademikException;
use App\Models\Akademik\Nilai;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Alumni;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Kemahasiswaan\PoinMahasiswa;
use App\Models\Kemahasiswaan\Yudisium;
use App\Models\Keuangan\Tagihan;
use App\Models\Sdm\Staff;
use App\Models\TugasAkhir\TugasAkhir;
use App\Notifications\Kemahasiswaan\KelulusanDitetapkan;
use App\Services\Akademik\PerolehanAkademik;
use App\Services\Bridge\BridgeEventPublisher;
use App\Services\Notifikasi\Notifier;
use App\Services\Surat\SuratService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Graduation: the checklist, the decision, and everything that follows from it.
 *
 * Confirming a graduation is the single most consequential write in the system.
 * It closes a student's record, changes what PDDIKTI is told about them, and
 * creates the alumni row an IKU 1 tracer study starts from. So it happens in
 * one transaction, from one place, and only after every requirement is checked
 * against live data rather than against a form someone filled in.
 */
class YudisiumService
{
    public function __construct(
        private readonly BridgeEventPublisher $bridge,
        private readonly Notifier $notifier,
        private readonly SuratService $surat,
        private readonly PerolehanAkademik $perolehan,
    ) {}

    /**
     * Evaluates the graduation requirements against current records.
     *
     * Deliberately recomputed rather than read from a stored flag: a student's
     * eligibility changes when a grade is corrected or an invoice is settled,
     * and a stale flag is how someone graduates with an unpaid semester.
     */
    public function periksaSyarat(Mahasiswa $mahasiswa): SyaratKelulusan
    {
        // Delegating to the batch path rather than duplicating the rules. The
        // graduation checklist is the one calculation that must not have two
        // implementations that can drift: the screen would then show a student
        // as eligible while the confirmation refuses them, or worse, the other
        // way round.
        return $this->periksaSyaratBanyak(collect([$mahasiswa]))[$mahasiswa->id];
    }

    /**
     * The same checklist for many students, in a fixed number of queries.
     *
     * The per-student version costs three queries. Run over a cohort — which
     * is exactly what the graduation screen does — that is hundreds of round
     * trips for a page that renders one table.
     *
     * @param Collection<int, Mahasiswa> $mahasiswa
     * @return Collection<int, SyaratKelulusan> keyed by student id
     */
    public function periksaSyaratBanyak(Collection $mahasiswa): Collection
    {
        // Callers arrive with whatever shape their query produced — a plucked
        // relation is a plain collection and has no loadMissing.
        $mahasiswa = EloquentCollection::make($mahasiswa->all());
        $mahasiswa->loadMissing('prodi');

        $ids = $mahasiswa->pluck('id')->all();

        if ($ids === []) {
            return collect();
        }

        // One source for "what has this student earned", shared with the
        // transcript and the IPK. See PerolehanAkademik: this used to be a
        // third copy of the same best-attempt-per-course logic.
        $perolehan = $this->perolehan->untukBanyak($mahasiswa);

        $tunggakanPerMahasiswa = Tagihan::query()
            ->whereIn('mahasiswa_id', $ids)
            ->belumLunas()
            ->groupBy('mahasiswa_id')
            ->pluck(DB::raw('SUM(total - terbayar)'), 'mahasiswa_id');

        // A grade still being entered can move the IPK after graduation is
        // confirmed, which would leave the diploma disagreeing with the record.
        $adaNilaiBelumFinal = Nilai::query()
            ->whereIn('mahasiswa_id', $ids)
            ->where('is_final', false)
            ->distinct()
            ->pluck('mahasiswa_id')
            ->flip();

        // Ordered ascending so keyBy keeps the most recent one, in the rare
        // case a student has completed more than one.
        $tugasAkhirSelesai = TugasAkhir::query()
            ->whereIn('mahasiswa_id', $ids)
            ->where('status', TugasAkhirStatus::Selesai->value)
            ->orderBy('id')
            ->get()
            ->keyBy('mahasiswa_id');

        /*
         * Verified achievement points, one grouped query for the cohort.
         *
         * Only the achievement ledger is read. Violations are a separate record
         * with their own consequences, and subtracting them here would let a
         * student earn their way out of a sanction.
         */
        $poinPrestasi = PoinMahasiswa::query()
            ->whereIn('mahasiswa_id', $ids)
            ->diakui()
            ->jenis(JenisPoin::Prestasi)
            ->groupBy('mahasiswa_id')
            ->pluck(DB::raw('SUM(poin)'), 'mahasiswa_id');

        return $mahasiswa->mapWithKeys(fn (Mahasiswa $m): array => [
            $m->id => $this->susunSyarat(
                $m,
                $perolehan->get($m->id) ?? collect(),
                (int) ($tunggakanPerMahasiswa[$m->id] ?? 0),
                $adaNilaiBelumFinal->has($m->id),
                $tugasAkhirSelesai->get($m->id),
                (int) ($poinPrestasi[$m->id] ?? 0),
            ),
        ]);
    }

    /**
     * The requirement rules themselves, over data that is already in memory.
     *
     * @param Collection<int, PerolehanBaris> $perolehan
     */
    private function susunSyarat(
        Mahasiswa $mahasiswa,
        Collection $perolehan,
        int $tunggakan,
        bool $belumFinal,
        ?TugasAkhir $tugasAkhir = null,
        int $poinPrestasi = 0,
    ): SyaratKelulusan {
        $angka = $this->perolehan->ringkas($perolehan);

        $sks = $angka['sksLulus'];
        $ipk = $angka['ipk'];

        $sksSyarat = (int) ($mahasiswa->prodi->sks_lulus ?: config('academic.graduation.min_credits'));
        $ipkSyarat = (float) config('academic.graduation.min_gpa');

        /*
         * The final project.
         *
         * Omitted entirely — rather than shown as an automatic pass — for
         * programmes that do not require one, so persenSelesai() stays honest
         * and nobody reads a satisfied row that was never a requirement.
         *
         * For everyone else this is the row that makes a diploma title
         * checkable. Before the tugas_akhir tables existed the title was free
         * text typed at this moment, which meant graduating without an examined
         * piece of work left no trace anywhere.
         */
        $syaratTugasAkhir = (bool) $mahasiswa->prodi->wajib_tugas_akhir
            ? [[
                'kode' => 'tugas_akhir',
                'label' => $mahasiswa->prodi->jenjang->sebutanTugasAkhir().' selesai',
                'terpenuhi' => $tugasAkhir !== null,
                'keterangan' => $tugasAkhir !== null
                    ? 'Lulus sidang, nilai '.number_format((float) $tugasAkhir->nilai_akhir, 2, ',', '.')
                    : 'Belum ada tugas akhir yang dinyatakan selesai',
            ]]
            : [];

        /*
         * Poin kemahasiswaan (SKKM).
         *
         * Omitted entirely when the campus sets no minimum, rather than shown
         * as an automatic pass — the same reasoning as the final project above.
         * A green row for a requirement that never existed makes
         * persenSelesai() lie and invites somebody to trust it.
         */
        $poinSyarat = (int) config('kemahasiswaan.prestasi.minimum_lulus', 0);

        $syaratPoin = $poinSyarat > 0
            ? [[
                'kode' => 'poin_kemahasiswaan',
                'label' => 'Poin kemahasiswaan',
                'terpenuhi' => $poinPrestasi >= $poinSyarat,
                'keterangan' => "{$poinPrestasi} dari {$poinSyarat} poin terverifikasi",
            ]]
            : [];

        return new SyaratKelulusan(
            sksLulus: $sks,
            sksSyarat: $sksSyarat,
            ipk: $ipk,
            ipkSyarat: $ipkSyarat,
            tunggakan: $tunggakan,
            adaNilaiBelumFinal: $belumFinal,
            rincian: [
                ...$syaratTugasAkhir,
                ...$syaratPoin,
                [
                    'kode' => 'sks',
                    'label' => 'Total SKS lulus',
                    'terpenuhi' => $sks >= $sksSyarat,
                    'keterangan' => "{$sks} dari {$sksSyarat} SKS",
                ],
                [
                    'kode' => 'ipk',
                    'label' => 'IPK minimum',
                    'terpenuhi' => $ipk >= $ipkSyarat,
                    'keterangan' => number_format($ipk, 2, ',', '.').' dari '.number_format($ipkSyarat, 2, ',', '.'),
                ],
                [
                    'kode' => 'keuangan',
                    'label' => 'Bebas tanggungan keuangan',
                    'terpenuhi' => $tunggakan <= 0,
                    'keterangan' => $tunggakan > 0
                        ? 'Sisa Rp'.number_format($tunggakan, 0, ',', '.')
                        : 'Tidak ada tunggakan',
                ],
                [
                    'kode' => 'nilai_final',
                    'label' => 'Seluruh nilai sudah final',
                    'terpenuhi' => !$belumFinal,
                    'keterangan' => $belumFinal ? 'Masih ada nilai belum difinalisasi' : 'Lengkap',
                ],
                [
                    'kode' => 'status',
                    'label' => 'Status mahasiswa aktif',
                    'terpenuhi' => $mahasiswa->status === StudentStatus::Aktif,
                    'keterangan' => $mahasiswa->status->label(),
                ],
            ],
        );
    }

    /** Registers a graduation proposal, which staff later confirm. */
    public function ajukan(Mahasiswa $mahasiswa, TahunAkademik $term, ?string $judulTugasAkhir = null): Yudisium
    {
        $syarat = $this->periksaSyarat($mahasiswa);

        if (!$syarat->memenuhi()) {
            throw new AturanAkademikException(sprintf(
                'Syarat kelulusan belum terpenuhi: %s.',
                implode(', ', $syarat->belumTerpenuhi()),
            ));
        }

        /*
         * The title comes from the examined record, not from a keyboard.
         *
         * Retyping it here is how a diploma ends up carrying a title that
         * matches nothing: no supervisor signed off on that string and no panel
         * examined it, so there is nothing to check it against. The parameter
         * survives only for records migrated from a previous system, where
         * there is no project row to read.
         */
        $tugasAkhir = TugasAkhir::query()
            ->where('mahasiswa_id', $mahasiswa->id)
            ->where('status', TugasAkhirStatus::Selesai->value)
            ->orderByDesc('id')
            ->first();

        return Yudisium::updateOrCreate(
            ['mahasiswa_id' => $mahasiswa->id],
            [
                'tahun_akademik_id' => $term->id,
                'total_sks' => $syarat->sksLulus,
                'ipk' => $syarat->ipk,
                'predikat' => Yudisium::predikatUntuk($syarat->ipk),
                'judul_tugas_akhir' => $tugasAkhir?->judul ?? $judulTugasAkhir,
                'status' => 'diajukan',
            ],
        );
    }

    /**
     * Confirms a graduation.
     *
     * Requirements are re-checked here, not trusted from the proposal: weeks
     * can pass between proposing and confirming, and a grade correction in
     * between must not slip through.
     */
    public function tetapkan(Yudisium $yudisium, Staff $staff, ?string $nomorSk = null): Yudisium
    {
        if ($yudisium->status === 'ditetapkan') {
            throw new AturanAkademikException('Kelulusan mahasiswa ini sudah ditetapkan.');
        }

        $mahasiswa = $yudisium->mahasiswa;
        $syarat = $this->periksaSyarat($mahasiswa);

        if (!$syarat->memenuhi()) {
            throw new AturanAkademikException(sprintf(
                'Syarat kelulusan tidak lagi terpenuhi: %s.',
                implode(', ', $syarat->belumTerpenuhi()),
            ));
        }

        DB::transaction(function () use ($yudisium, $mahasiswa, $staff, $nomorSk, $syarat): void {
            $yudisium->update([
                'status' => 'ditetapkan',
                'nomor_sk' => $nomorSk ?? $yudisium->nomor_sk,
                'tanggal_yudisium' => now(),
                'tanggal_lulus' => now(),
                'total_sks' => $syarat->sksLulus,
                'ipk' => $syarat->ipk,
                'predikat' => Yudisium::predikatUntuk($syarat->ipk),
                'ditetapkan_by_staff_id' => $staff->id,
                'ditetapkan_at' => now(),
            ]);

            // The status change is what PDDIKTI eventually reports, so it is
            // part of the same transaction as the decision itself.
            $mahasiswa->update(['status' => StudentStatus::Lulus]);

            Alumni::updateOrCreate(
                ['mahasiswa_id' => $mahasiswa->id],
                [
                    'tahun_lulus' => now()->year,
                    'email_pribadi' => $mahasiswa->email,
                    'telepon' => $mahasiswa->telepon,
                    'status_pekerjaan' => 'belum',
                ],
            );
        });

        // Open Campus's tracer study (IKU 1) starts from this event.
        $this->bridge->publish('student.graduated', [
            'mahasiswa_uuid' => $mahasiswa->uuid,
            'nim' => $mahasiswa->nim,
            'nama' => $mahasiswa->nama,
            'prodi' => $mahasiswa->prodi->kode,
            'tanggal_lulus' => now()->toDateString(),
            'total_sks' => $syarat->sksLulus,
            'ipk' => $syarat->ipk,
            'predikat' => Yudisium::predikatUntuk($syarat->ipk),
        ]);

        $this->bridge->publish('student.status_changed', [
            'mahasiswa_uuid' => $mahasiswa->uuid,
            'nim' => $mahasiswa->nim,
            'status_lama' => StudentStatus::Aktif->value,
            'status_baru' => StudentStatus::Lulus->value,
        ]);

        /*
         * The diploma supplement is issued here, not asked for later.
         *
         * Regulation requires every graduate to receive one alongside the
         * diploma. Leaving it as a request means it gets issued to the graduates
         * who knew to ask — which is not what "every graduate" means, and the
         * ones who did not know are the ones least able to chase it.
         *
         * Failure to issue must not undo a confirmed graduation, so this cannot
         * throw: terbitkanSkpi() returns null when the supplement is not
         * possible, and the screen offers a manual retry.
         */
        $this->surat->terbitkanSkpi($mahasiswa->refresh(), $staff);

        $this->notifier->kirim($mahasiswa, new KelulusanDitetapkan($yudisium->refresh()));

        return $yudisium->refresh();
    }

    /** Cancels a confirmed graduation — an exceptional, audited correction. */
    public function batalkan(Yudisium $yudisium, Staff $staff, string $alasan): Yudisium
    {
        if (blank($alasan)) {
            throw new AturanAkademikException('Pembatalan penetapan kelulusan wajib disertai alasan.');
        }

        DB::transaction(function () use ($yudisium, $alasan): void {
            $yudisium->update(['status' => 'diajukan', 'tanggal_lulus' => null, 'ditetapkan_at' => null]);
            $yudisium->mahasiswa->update(['status' => StudentStatus::Aktif]);
            $yudisium->recordActivity('cancelled', 'Penetapan kelulusan dibatalkan. Alasan: '.$alasan);
        });

        return $yudisium->refresh();
    }

    /**
     * Students who currently meet every requirement but have no proposal yet.
     *
     * @return Collection<int, array{mahasiswa: Mahasiswa, syarat: SyaratKelulusan}>
     */
    public function kandidat(): Collection
    {
        // Narrowed before the full checklist runs, not after.
        //
        // The checklist is honest but expensive: it reads every finalised grade
        // a student has. Running it across an entire active population — five
        // thousand people on a mid-size campus — means loading the whole grade
        // table to discover that almost nobody is close to graduating.
        //
        // Credits are the requirement that fails first and the only one that
        // can be checked in a single aggregate, so it goes first. Everything
        // else is still evaluated in full on the shortlist that survives.
        //
        // The pre-filter counts repeats more than once, so it over-admits. That
        // is the safe direction for a filter whose only job is to exclude the
        // obviously-not-close: nobody who qualifies is dropped, and the real
        // checklist rejects the extras.
        // The lowest threshold anywhere on campus, not the configured default.
        //
        // Each programme may set its own sks_lulus, and some sit below the
        // global figure. Filtering on the default alone would silently drop
        // every candidate from a programme with a lower requirement — they
        // would never appear on the graduation screen at all.
        $ambangSks = min(
            (int) config('academic.graduation.min_credits'),
            (int) (Prodi::query()->where('sks_lulus', '>', 0)->min('sks_lulus')
                ?? config('academic.graduation.min_credits')),
        );

        $kandidat = DB::table('nilai')
            ->join('krs_detail', 'krs_detail.id', '=', 'nilai.krs_detail_id')
            ->where('nilai.is_final', true)
            ->whereNotNull('nilai.nilai_huruf')
            ->whereIn('nilai.nilai_huruf', GradeLetter::passingValues())
            ->groupBy('nilai.mahasiswa_id')
            ->havingRaw('SUM(krs_detail.sks) >= ?', [$ambangSks])
            ->pluck('nilai.mahasiswa_id');

        if ($kandidat->isEmpty()) {
            return collect();
        }

        $mahasiswa = Mahasiswa::query()
            ->with(['prodi'])
            ->whereIn('id', $kandidat)
            ->where('status', StudentStatus::Aktif->value)
            ->whereDoesntHave('yudisium')
            ->get();

        $syarat = $this->periksaSyaratBanyak($mahasiswa);

        return $mahasiswa
            ->map(fn (Mahasiswa $m): array => ['mahasiswa' => $m, 'syarat' => $syarat[$m->id]])
            ->filter(fn (array $baris): bool => $baris['syarat']->memenuhi())
            ->values();
    }
}

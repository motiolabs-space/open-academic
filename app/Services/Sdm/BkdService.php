<?php

declare(strict_types=1);

namespace App\Services\Sdm;

use App\DTOs\Sdm\BarisBeban;
use App\Enums\KesimpulanBkd;
use App\Enums\StatusBkd;
use App\Enums\UnsurBkd;
use App\Exceptions\AturanAkademikException;
use App\Models\Akademik\TahunAkademik;
use App\Models\Sdm\BkdBaris;
use App\Models\Sdm\BkdLaporan;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\Staff;
use App\Notifications\Sdm\BkdDinilai;
use App\Services\Notifikasi\Notifier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The life of a workload report: submitted, assessed, endorsed.
 *
 * The load-bearing moment is submission, and it is a copy rather than a state
 * change. Everything before it is a live view over classes, supervision, and
 * examining; everything after it is a snapshot.
 *
 * That matters because the report is assessed and the assessment gates money.
 * If the lines read through to live data, a class reassigned in April would
 * silently rewrite an assessment signed in March, and the signature would end
 * up attached to figures nobody ever saw. Same reasoning as freezing the text
 * of an issued letter.
 */
class BkdService
{
    public function __construct(
        private readonly BebanKerjaService $beban,
        private readonly Notifier $notifier,
    ) {}

    /**
     * The working sheet — computed, stored nowhere.
     *
     * Called on every view while the report is still editable. Once submitted,
     * read the frozen lines off the report instead; `barisTersimpan()` below is
     * the counterpart.
     *
     * @return Collection<int, BarisBeban>
     */
    public function lembarKerja(Dosen $dosen, TahunAkademik $term): Collection
    {
        return $this->beban->hitung($dosen, $term);
    }

    /** Finds or starts this lecturer's report for a term. */
    public function laporan(Dosen $dosen, TahunAkademik $term): BkdLaporan
    {
        try {
            return BkdLaporan::firstOrCreate(
                ['dosen_id' => $dosen->id, 'tahun_akademik_id' => $term->id],
                ['status' => StatusBkd::Draft],
            );
        } catch (UniqueConstraintViolationException) {
            // Two tabs on deadline day. The index refused the second insert, so
            // the first one is the report.
            return BkdLaporan::query()
                ->where('dosen_id', $dosen->id)
                ->where('tahun_akademik_id', $term->id)
                ->firstOrFail();
        }
    }

    /**
     * Submits, freezing the sheet as it stands.
     *
     * Threshold breaches do not block submission. A lecturer who genuinely fell
     * short of twelve credits has to report that — refusing the report would
     * leave the semester unreported rather than reported as short, and the
     * assessors are the ones who decide what it means.
     */
    public function ajukan(BkdLaporan $laporan): BkdLaporan
    {
        if (!$laporan->status->dapatDisunting()) {
            throw new AturanAkademikException(
                'Laporan ini sudah diajukan dan tidak dapat diajukan ulang.',
            );
        }

        $baris = $this->lembarKerja($laporan->dosen, $laporan->tahunAkademik);

        if ($baris->isEmpty()) {
            throw new AturanAkademikException(
                'Belum ada satu pun kegiatan pada semester ini, sehingga belum ada yang dapat dilaporkan.',
            );
        }

        $ringkas = $this->beban->ringkas($baris);

        return DB::transaction(function () use ($laporan, $baris, $ringkas): BkdLaporan {
            // A returned report is being resubmitted, so the previous snapshot
            // goes. Keeping both would leave two sets of lines under one report
            // and no way to say which was assessed.
            $laporan->baris()->delete();

            foreach ($baris->values() as $urutan => $satu) {
                BkdBaris::create([
                    'bkd_laporan_id' => $laporan->id,
                    'unsur' => $satu->unsur,
                    'kegiatan' => $satu->kegiatan,
                    'rincian' => $satu->rincian,
                    'sks_ratus' => $satu->sksRatus,
                    'otomatis' => $satu->otomatis,
                    'penugasan_dosen_id' => $satu->penugasanId,
                    'bukti_path' => $satu->buktiPath,
                    'urutan' => $urutan,
                ]);
            }

            $laporan->update([
                'status' => StatusBkd::Diajukan,
                'diajukan_at' => now(),
                'sks_pendidikan' => $ringkas[UnsurBkd::Pendidikan->value],
                'sks_penelitian' => $ringkas[UnsurBkd::Penelitian->value],
                'sks_pengabdian' => $ringkas[UnsurBkd::Pengabdian->value],
                'sks_penunjang' => $ringkas[UnsurBkd::Penunjang->value],
                'sks_total' => $ringkas['total'],

                // Cleared on resubmission: a previous assessment describes a
                // previous snapshot.
                'kesimpulan' => null,
                'catatan_asesor' => null,
                'dinilai_at' => null,
            ]);

            return $laporan->fresh(['baris']);
        });
    }

    /**
     * Names the two assessors.
     *
     * Assignment is separate from assessment so that the report can be routed
     * before anybody has looked at it, and so that the pair is on record.
     */
    public function tetapkanAsesor(BkdLaporan $laporan, Dosen $pertama, ?Dosen $kedua = null): void
    {
        // Assessing one's own report is not a conflict to be managed, it is the
        // absence of an assessment.
        foreach (array_filter([$pertama, $kedua]) as $asesor) {
            if ($asesor->id === $laporan->dosen_id) {
                throw new AturanAkademikException(
                    'Dosen tidak dapat menjadi asesor atas laporannya sendiri.',
                );
            }
        }

        if ($kedua !== null && $kedua->id === $pertama->id) {
            throw new AturanAkademikException(
                'Kedua asesor harus orang yang berbeda.',
            );
        }

        $laporan->update([
            'asesor_1_dosen_id' => $pertama->id,
            'asesor_2_dosen_id' => $kedua?->id,
        ]);
    }

    /**
     * Records the assessors' conclusion.
     *
     * A note is required whenever the conclusion is anything short of a pass.
     * "Tidak memenuhi" without a reason sends the lecturer to an office to ask
     * what went wrong, and the answer is the one thing the assessor is there to
     * produce.
     */
    public function nilai(
        BkdLaporan $laporan,
        Dosen $asesor,
        KesimpulanBkd $kesimpulan,
        ?string $catatan = null,
    ): void {
        if (!$laporan->dinilaiOleh($asesor)) {
            throw new AturanAkademikException(
                'Anda bukan asesor untuk laporan ini.',
            );
        }

        if ($laporan->status !== StatusBkd::Diajukan) {
            throw new AturanAkademikException(
                'Hanya laporan berstatus diajukan yang dapat dinilai.',
            );
        }

        if ($kesimpulan !== KesimpulanBkd::Memenuhi && blank($catatan)) {
            throw new AturanAkademikException(
                'Kesimpulan selain "memenuhi" wajib disertai catatan.',
            );
        }

        $laporan->update([
            'status' => StatusBkd::Dinilai,
            'kesimpulan' => $kesimpulan,
            'catatan_asesor' => $catatan,
            'dinilai_at' => now(),
        ]);

        $this->notifier->kirim($laporan->dosen, new BkdDinilai($laporan->fresh()));
    }

    /**
     * Sends the report back for correction.
     *
     * The point of returning rather than failing: a report with a miscategorised
     * line should be fixed, not marked as not meeting requirements for a whole
     * semester.
     */
    public function kembalikan(BkdLaporan $laporan, Dosen $asesor, string $alasan): void
    {
        if (!$laporan->dinilaiOleh($asesor)) {
            throw new AturanAkademikException('Anda bukan asesor untuk laporan ini.');
        }

        if ($laporan->status !== StatusBkd::Diajukan) {
            throw new AturanAkademikException(
                'Hanya laporan berstatus diajukan yang dapat dikembalikan.',
            );
        }

        $laporan->update([
            'status' => StatusBkd::Dikembalikan,
            'catatan_asesor' => $alasan,
            'dinilai_at' => null,
        ]);

        $this->notifier->kirim($laporan->dosen, new BkdDinilai($laporan->fresh()));
    }

    /**
     * The institution's endorsement, which is what actually closes the report.
     *
     * Only after assessment. Endorsing an unassessed report would make the
     * assessors decorative, and the signature is the campus vouching for a
     * judgement that was never made.
     */
    public function sahkan(BkdLaporan $laporan, Staff $staff): void
    {
        if ($laporan->status !== StatusBkd::Dinilai) {
            throw new AturanAkademikException(
                'Laporan hanya dapat disahkan setelah dinilai asesor.',
            );
        }

        $laporan->update([
            'status' => StatusBkd::Disahkan,
            'disahkan_by_staff_id' => $staff->id,
            'disahkan_at' => now(),
        ]);
    }

    /**
     * Lecturers who owe a report for a term, and have not filed one.
     *
     * @return Collection<int, Dosen>
     */
    public function belumMelapor(TahunAkademik $term): Collection
    {
        return $this->wajibMelapor()
            ->whereDoesntHave('laporanBkd', fn ($q) => $q
                ->where('tahun_akademik_id', $term->id)
                ->where('status', '!=', StatusBkd::Draft->value))
            ->orderBy('nama')
            ->get();
    }

    /**
     * The population obliged to report.
     *
     * Certification is the reason the report exists, so by default only holders
     * are asked. A campus may widen it — see config('bkd.wajib') — but that is
     * a choice to impose paperwork the regulation does not.
     *
     * @return Builder<Dosen>
     */
    public function wajibMelapor()
    {
        return Dosen::aktif()->when(
            config('bkd.wajib') === 'serdos',
            fn ($q) => $q->whereHas('sertifikasi', fn ($s) => $s->serdos()),
        );
    }
}

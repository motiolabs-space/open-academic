<?php

declare(strict_types=1);

namespace App\Services\Kemahasiswaan;

use App\Enums\HasilEvaluasi;
use App\Enums\KeputusanEvaluasi;
use App\Enums\StudentStatus;
use App\Exceptions\AturanAkademikException;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\EvaluasiStudi;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Kemahasiswaan\StatusMahasiswa;
use App\Models\Sdm\Staff;
use App\Notifications\Kemahasiswaan\PeringatanAkademik;
use App\Services\Notifikasi\Notifier;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Evaluasi studi — the checkpoints at which a campus decides who continues.
 *
 * **This service counts. It never decides.** Every sweep produces findings with
 * `keputusan = menunggu`, and nothing here changes a student's status. Ending
 * somebody's degree is not an outcome a scheduled job may reach unattended, and
 * the fastest way to build a system that does exactly that is to let the sweep
 * write the verdict "because the rule is unambiguous". The rule is unambiguous;
 * the situations are not.
 *
 * Figures come from the **frozen** per-term record, so an evaluation matches the
 * KHS the student was actually given rather than a recomputation that may have
 * drifted since.
 */
class EvaluasiStudiService
{
    public function __construct(private readonly Notifier $notifier) {}

    /**
     * Evaluates every student with a finalised record in this term.
     *
     * @return array{diperiksa: int, temuan: int, dilewati: int}
     */
    public function jalankan(TahunAkademik $term): array
    {
        $catatan = StatusMahasiswa::query()
            ->with('mahasiswa')
            ->where('tahun_akademik_id', $term->id)
            ->where('is_final', true)
            ->get();

        $tempuh = $this->semesterTempuh($term, $catatan->pluck('mahasiswa_id'));

        $hasil = ['diperiksa' => 0, 'temuan' => 0, 'dilewati' => 0];

        foreach ($catatan as $baris) {
            $mahasiswa = $baris->mahasiswa;

            /*
             * Students whose enrolment already ended are skipped.
             *
             * Evaluating a graduate against a credit threshold produces a
             * finding about somebody who has already left, and an operator
             * cannot act on it — the queue fills with noise and the real cases
             * stop being visible.
             */
            if ($mahasiswa === null || $mahasiswa->status->isTerminal()) {
                $hasil['dilewati']++;

                continue;
            }

            $hasil['diperiksa']++;

            foreach ($this->periksa($baris, (int) ($tempuh[$baris->mahasiswa_id] ?? 0)) as $temuan) {
                if ($this->catat($baris, $temuan) !== null) {
                    $hasil['temuan']++;
                }
            }
        }

        return $hasil;
    }

    /**
     * Every rule that applies to one term record.
     *
     * @return array<int, array<string, mixed>>
     */
    private function periksa(StatusMahasiswa $baris, int $tempuh): array
    {
        $temuan = [];

        foreach ((array) config('academic.evaluasi.tahap', []) as $tahap) {
            if ($tempuh !== (int) $tahap['semester_ke']) {
                continue;
            }

            $lolos = $baris->sks_kumulatif >= (int) $tahap['min_sks']
                && (float) $baris->ipk >= (float) $tahap['min_ipk'];

            $temuan[] = [
                'tahap' => (string) $tahap['nama'],
                'syarat_sks' => (int) $tahap['min_sks'],
                'syarat_ipk' => (float) $tahap['min_ipk'],
                'temuan' => $lolos ? HasilEvaluasi::Lolos : HasilEvaluasi::TidakMemenuhi,
            ];
        }

        $maksimum = (int) config('academic.evaluasi.masa_studi_maksimum', 14);

        if ($maksimum > 0 && $tempuh >= $maksimum) {
            $temuan[] = [
                'tahap' => 'Batas Masa Studi',
                'syarat_sks' => null,
                'syarat_ipk' => null,

                // At the limit is a warning; past it is a failure. The student
                // in their final permitted term still has that term to finish.
                'temuan' => $tempuh > $maksimum
                    ? HasilEvaluasi::TidakMemenuhi
                    : HasilEvaluasi::Peringatan,
            ];
        }

        $ambangIps = (float) config('academic.evaluasi.peringatan_ips', 0);

        if ($ambangIps > 0 && (float) $baris->ips < $ambangIps) {
            $temuan[] = [
                // Null tahap: a running check, not a milestone. The unique key
                // treats it as its own slot per term.
                'tahap' => null,
                'syarat_sks' => null,
                'syarat_ipk' => $ambangIps,
                'temuan' => HasilEvaluasi::Peringatan,
            ];
        }

        return $temuan;
    }

    /**
     * Writes one finding, without ever disturbing a decision already made.
     *
     * @param array<string, mixed> $temuan
     */
    private function catat(StatusMahasiswa $baris, array $temuan): ?EvaluasiStudi
    {
        $kunci = [
            'mahasiswa_id' => $baris->mahasiswa_id,
            'tahun_akademik_id' => $baris->tahun_akademik_id,
            'tahap' => $temuan['tahap'],
        ];

        $ada = EvaluasiStudi::where($kunci)->first();

        /*
         * A decided finding is left exactly as it was.
         *
         * Re-running the sweep after somebody has ruled must not quietly reset
         * their ruling to "menunggu" — and it must not restate the figures
         * either, because the decision was made against the figures as they
         * stood when it was made.
         */
        if ($ada !== null && $ada->keputusan !== KeputusanEvaluasi::Menunggu) {
            return null;
        }

        $evaluasi = EvaluasiStudi::updateOrCreate($kunci, [
            'semester_ke' => $baris->semester_ke,
            'sks_kumulatif' => $baris->sks_kumulatif,
            'ipk' => $baris->ipk,
            'ips' => $baris->ips,
            'syarat_sks' => $temuan['syarat_sks'],
            'syarat_ipk' => $temuan['syarat_ipk'],
            'temuan' => $temuan['temuan'],
        ]);

        if ($temuan['temuan']->perluDitindak()) {
            /*
             * Deduped on the finding, not on the run.
             *
             * A sweep re-run for the same term must not notify the student
             * again about the same problem; a genuinely new checkpoint must.
             */
            $this->notifier->kirimSekali(
                $baris->mahasiswa,
                'evaluasi:'.$evaluasi->id,
                new PeringatanAkademik($evaluasi),
            );
        }

        return $evaluasi;
    }

    /**
     * Terms actually attended, per student, up to and including this one.
     *
     * **Leave does not count**, and that is the point of leave. Counting it
     * would penalise a student for the illness or the unpaid fee that the
     * leave existed to accommodate — and it would do so silently, by moving
     * their checkpoint a semester earlier than the rule intends.
     *
     * One grouped query for the whole cohort rather than one per student: this
     * runs over an entire campus at term close.
     *
     * @param Collection<int, int> $mahasiswaIds
     * @return array<int, int>
     */
    private function semesterTempuh(TahunAkademik $term, Collection $mahasiswaIds): array
    {
        if ($mahasiswaIds->isEmpty()) {
            return [];
        }

        return DB::table('status_mahasiswa')
            ->join('tahun_akademik', 'tahun_akademik.id', '=', 'status_mahasiswa.tahun_akademik_id')
            ->whereIn('status_mahasiswa.mahasiswa_id', $mahasiswaIds->unique()->all())
            ->where('tahun_akademik.kode', '<=', $term->kode)
            ->where('status_mahasiswa.status', '!=', StudentStatus::Cuti->value)
            ->groupBy('status_mahasiswa.mahasiswa_id')
            ->pluck(DB::raw('count(*)'), 'status_mahasiswa.mahasiswa_id')
            ->map(fn ($n): int => (int) $n)
            ->all();
    }

    /**
     * Records a person's decision about a finding.
     *
     * The only path by which an evaluation may change a student's status, and
     * it requires a member of staff and a written reason. Both because the
     * campus will be asked to account for this one, and because the reason is
     * the only part of the record that explains a decision taken *against* the
     * rule — which is a decision the campus makes often, and should.
     */
    public function putuskan(
        EvaluasiStudi $evaluasi,
        KeputusanEvaluasi $keputusan,
        Staff $staff,
        string $catatan,
    ): EvaluasiStudi {
        if ($keputusan === KeputusanEvaluasi::Menunggu) {
            throw new AturanAkademikException('"Menunggu keputusan" bukan keputusan yang dapat dicatat.');
        }

        if ($evaluasi->keputusan !== KeputusanEvaluasi::Menunggu) {
            throw new AturanAkademikException(sprintf(
                'Evaluasi ini sudah diputuskan sebagai "%s" pada %s.',
                $evaluasi->keputusan->label(),
                $evaluasi->diputuskan_at?->translatedFormat('d F Y') ?? '-',
            ));
        }

        if ($keputusan->wajibBeralasan() && blank($catatan)) {
            throw new AturanAkademikException('Keputusan evaluasi wajib disertai alasan tertulis.');
        }

        return DB::transaction(function () use ($evaluasi, $keputusan, $staff, $catatan): EvaluasiStudi {
            $evaluasi->update([
                'keputusan' => $keputusan,
                'diputuskan_by_staff_id' => $staff->id,
                'diputuskan_at' => now(),
                'catatan' => $catatan,
            ]);

            if ($keputusan->mengakhiriStudi()) {
                $evaluasi->mahasiswa->update([
                    'status' => $keputusan === KeputusanEvaluasi::DropOut
                        ? StudentStatus::DropOut
                        : StudentStatus::Keluar,
                ]);
            }

            return $evaluasi->refresh();
        });
    }

    /**
     * Undoes a decision.
     *
     * Exists because the alternative is worse: without it, a decision recorded
     * against the wrong student is corrected by editing the row directly, which
     * leaves no trace that it ever said something else. Reinstating a status is
     * deliberately *not* automatic — a student wrongly dropped out is put back
     * by the same screen that manages status, where that act is itself audited.
     */
    public function batalkanKeputusan(EvaluasiStudi $evaluasi, Staff $staff, string $alasan): EvaluasiStudi
    {
        if ($evaluasi->keputusan === KeputusanEvaluasi::Menunggu) {
            throw new AturanAkademikException('Evaluasi ini belum diputuskan.');
        }

        if (blank($alasan)) {
            throw new AturanAkademikException('Pembatalan keputusan wajib disertai alasan.');
        }

        $evaluasi->update([
            'keputusan' => KeputusanEvaluasi::Menunggu,
            'diputuskan_by_staff_id' => null,
            'diputuskan_at' => null,
            'catatan' => trim($evaluasi->catatan."\n\n[Dibatalkan oleh {$staff->nama}] ".$alasan),
        ]);

        return $evaluasi->refresh();
    }

    /**
     * The queue: findings nobody has acted on.
     *
     * @return Collection<int, EvaluasiStudi>
     */
    public function antrean(?TahunAkademik $term = null): Collection
    {
        return EvaluasiStudi::query()
            ->with(['mahasiswa.prodi', 'tahunAkademik'])
            ->perluDitindak()
            ->when($term, fn ($q) => $q->where('tahun_akademik_id', $term->id))
            ->orderBy('temuan')
            ->orderByDesc('semester_ke')
            ->get();
    }

    /** @return Collection<int, EvaluasiStudi> */
    public function riwayat(Mahasiswa $mahasiswa): Collection
    {
        return EvaluasiStudi::query()
            ->with(['tahunAkademik', 'diputuskanOleh'])
            ->where('mahasiswa_id', $mahasiswa->id)
            ->orderByDesc('semester_ke')
            ->get();
    }
}

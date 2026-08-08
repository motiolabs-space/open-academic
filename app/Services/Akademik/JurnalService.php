<?php

declare(strict_types=1);

namespace App\Services\Akademik;

use App\Enums\AttendanceStatus;
use App\Exceptions\AturanAkademikException;
use App\Models\Akademik\KelasKuliah;
use App\Models\Akademik\PertemuanKelas;
use App\Models\Akademik\Rps;
use App\Models\Sdm\Dosen;
use Illuminate\Support\Collection;

/**
 * The teaching journal — what was actually delivered, meeting by meeting.
 *
 * Attendance answers *who was there*. This answers *what was taught*, and that
 * is the half a monitoring visit asks about — and the half still kept on paper
 * at most campuses.
 *
 * The journal is deliberately allowed to disagree with the plan. Teaching slips:
 * a public holiday pushes week five into week six, two planned sessions get
 * merged, a guest lecture replaces a topic. Forcing the journal to match the RPS
 * would delete exactly the information somebody is looking for when they ask why
 * only twelve of sixteen sessions were delivered.
 */
class JurnalService
{
    /**
     * Records what was taught, and freezes the attendance count alongside it.
     *
     * The counts are a snapshot, never recomputed. A journal is a statement
     * about one afternoon; recalculating it months later — after an attendance
     * correction, or after a student's enrolment was withdrawn — would change a
     * signed record of what happened in a room.
     */
    public function isi(
        PertemuanKelas $pertemuan,
        Dosen $dosen,
        string $materi,
        ?int $rpsPertemuanId = null,
        ?string $catatan = null,
    ): PertemuanKelas {
        if (blank($materi)) {
            throw new AturanAkademikException('Materi yang diajarkan wajib diisi.');
        }

        if (!$pertemuan->kelasKuliah->dosen->contains('id', $dosen->id)) {
            throw new AturanAkademikException('Anda tidak mengampu kelas ini.');
        }

        $presensi = $pertemuan->presensi()->get();

        $pertemuan->update([
            'materi' => $materi,
            'catatan' => $catatan,
            'rps_pertemuan_id' => $rpsPertemuanId,

            'jumlah_hadir' => $presensi
                ->filter(fn ($p): bool => $p->status !== AttendanceStatus::Alpa)
                ->count(),
            'jumlah_peserta' => $presensi->count(),

            'jurnal_diisi_at' => now(),
            'jurnal_oleh_dosen_id' => $dosen->id,

            // Filling in the journal is the act that declares a meeting held.
            // Before this, "terlaksana" was set by whoever took the register —
            // which recorded that somebody opened the screen, not that a class
            // happened.
            'is_terlaksana' => true,
        ]);

        return $pertemuan->refresh();
    }

    /**
     * How much of the plan a class has actually delivered.
     *
     * Two different numbers, and the gap between them is the finding:
     *
     *   - `terlaksana` — meetings held, from the register
     *   - `berjurnal` — meetings with a journal entry
     *
     * A class with fourteen held and four journalled has not taught less; it has
     * documented less. Reporting one number would hide which of the two problems
     * the campus has.
     *
     * @return array<string, mixed>
     */
    public function keterlaksanaan(KelasKuliah $kelas): array
    {
        $rencana = (int) config('academic.attendance.meetings_per_term', 16);

        $pertemuan = $kelas->pertemuan()->get();

        $terlaksana = $pertemuan->where('is_terlaksana', true)->count();
        $berjurnal = $pertemuan->whereNotNull('jurnal_diisi_at')->count();

        $rps = Rps::untuk($kelas->mata_kuliah_id, $kelas->tahun_akademik_id);

        return [
            'rencana' => $rencana,
            'terlaksana' => $terlaksana,
            'berjurnal' => $berjurnal,
            'persen_terlaksana' => $rencana > 0 ? round($terlaksana / $rencana * 100, 1) : 0.0,
            'persen_berjurnal' => $rencana > 0 ? round($berjurnal / $rencana * 100, 1) : 0.0,

            'ada_rps' => $rps !== null,

            /*
             * Planned sessions nothing has delivered yet.
             *
             * Only meaningful once a plan exists — without one there is nothing
             * to be behind on, and reporting "16 sessions missed" against a
             * course that never had a plan would be an accusation rather than a
             * measurement.
             */
            'pertemuan_belum_tersampaikan' => $rps === null
                ? collect()
                : $rps->pertemuan
                    ->whereNotIn('id', $pertemuan->pluck('rps_pertemuan_id')->filter())
                    ->values(),
        ];
    }

    /**
     * Classes whose journals are behind, for a programme to chase.
     *
     * @param Collection<int, KelasKuliah> $kelas
     * @return Collection<int, array<string, mixed>>
     */
    public function tertinggal(Collection $kelas, int $selisihMinimum = 2): Collection
    {
        return $kelas
            ->map(fn (KelasKuliah $k): array => [
                'kelas' => $k,
                'keterlaksanaan' => $this->keterlaksanaan($k),
            ])
            ->filter(fn (array $b): bool => $b['keterlaksanaan']['terlaksana']
                - $b['keterlaksanaan']['berjurnal'] >= $selisihMinimum)
            ->sortByDesc(fn (array $b): int => $b['keterlaksanaan']['terlaksana']
                - $b['keterlaksanaan']['berjurnal'])
            ->values();
    }
}

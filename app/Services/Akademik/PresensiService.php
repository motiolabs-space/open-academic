<?php

declare(strict_types=1);

namespace App\Services\Akademik;

use App\Enums\AttendanceStatus;
use App\Enums\KrsStatus;
use App\Exceptions\AturanAkademikException;
use App\Models\Akademik\KelasKuliah;
use App\Models\Akademik\PertemuanKelas;
use App\Models\Akademik\Presensi;
use App\Models\Kemahasiswaan\Mahasiswa;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Attendance recording and the eligibility rule it exists to serve.
 *
 * Marking presence is only half the job: the reason a campus tracks it at all
 * is the minimum-attendance requirement for sitting the final exam. That
 * threshold is computed here and consumed by the grading screen, so the rule
 * has exactly one definition.
 *
 * Excused absences (izin, sakit) count as attendance; only alpa does not.
 */
class PresensiService
{
    /** Creates the term's meetings for a class if they do not exist yet. */
    public function siapkanPertemuan(KelasKuliah $kelas): Collection
    {
        $jumlah = (int) config('academic.attendance.meetings_per_term');

        if ($kelas->pertemuan()->count() >= $jumlah) {
            return $kelas->pertemuan()->get();
        }

        $jadwal = $kelas->jadwal()->first();
        $mulai = $kelas->tahunAkademik->tanggal_mulai->copy()->startOfWeek()
            ->addDays(($jadwal?->hari ?? 1) - 1);

        for ($ke = 1; $ke <= $jumlah; $ke++) {
            PertemuanKelas::firstOrCreate(
                ['kelas_kuliah_id' => $kelas->id, 'pertemuan_ke' => $ke],
                [
                    'dosen_id' => $kelas->dosenPengampu()->first()?->id,
                    'tanggal' => (clone $mulai)->addWeeks($ke - 1),
                    'jam_mulai' => $jadwal?->jam_mulai,
                    'jam_selesai' => $jadwal?->jam_selesai,
                    'metode' => $kelas->mode === 'daring' ? 'daring' : 'tatap_muka',
                ],
            );
        }

        return $kelas->pertemuan()->get();
    }

    /**
     * Records attendance for one meeting in a single pass.
     *
     * @param array<int, string> $tanda mahasiswa id => AttendanceStatus value
     */
    public function catat(PertemuanKelas $pertemuan, array $tanda, string $sumber = 'dosen'): int
    {
        // A service cannot assume how its caller fetched the meeting. Asking
        // for the relation explicitly costs nothing when it is already loaded
        // and keeps the method usable from a loop over a class's meetings.
        $pertemuan->loadMissing('kelasKuliah');

        $pesertaSah = $this->pesertaKelas($pertemuan->kelasKuliah)->pluck('id')->all();

        return DB::transaction(function () use ($pertemuan, $tanda, $sumber, $pesertaSah): int {
            $tercatat = 0;

            foreach ($tanda as $mahasiswaId => $status) {
                // A mark for someone who never enrolled would quietly corrupt
                // the percentage the eligibility rule depends on.
                if (!in_array((int) $mahasiswaId, $pesertaSah, true)) {
                    continue;
                }

                Presensi::updateOrCreate(
                    ['pertemuan_kelas_id' => $pertemuan->id, 'mahasiswa_id' => (int) $mahasiswaId],
                    [
                        'status' => AttendanceStatus::from($status),
                        'waktu_absen' => now(),
                        'sumber' => $sumber,
                    ],
                );

                $tercatat++;
            }

            $pertemuan->update(['is_terlaksana' => true]);

            return $tercatat;
        });
    }

    /**
     * Opens a QR self-service window for a meeting.
     *
     * The token rotates and expires within minutes, so a screenshot forwarded
     * to an absent classmate stops working before it is useful.
     */
    public function bukaSesiQr(PertemuanKelas $pertemuan): PertemuanKelas
    {
        $pertemuan->update([
            'qr_token' => Str::random(48),
            'qr_expires_at' => now()->addSeconds((int) config('academic.attendance.qr_session_ttl')),
        ]);

        return $pertemuan->refresh();
    }

    public function tutupSesiQr(PertemuanKelas $pertemuan): PertemuanKelas
    {
        $pertemuan->update(['qr_token' => null, 'qr_expires_at' => null]);

        return $pertemuan->refresh();
    }

    /** A student scanning the code marks themselves present. */
    public function absenMandiri(string $token, Mahasiswa $mahasiswa): Presensi
    {
        $pertemuan = PertemuanKelas::with('kelasKuliah')->where('qr_token', $token)->first();

        if ($pertemuan === null || !$pertemuan->qrAktif()) {
            throw new AturanAkademikException('Sesi presensi tidak ditemukan atau sudah berakhir.');
        }

        if (!$this->pesertaKelas($pertemuan->kelasKuliah)->contains('id', $mahasiswa->id)) {
            throw new AturanAkademikException('Anda tidak terdaftar pada kelas ini.');
        }

        return Presensi::updateOrCreate(
            ['pertemuan_kelas_id' => $pertemuan->id, 'mahasiswa_id' => $mahasiswa->id],
            ['status' => AttendanceStatus::Hadir, 'waktu_absen' => now(), 'sumber' => 'qr'],
        );
    }

    /**
     * Attendance percentage against meetings actually held.
     *
     * Measured against held meetings rather than the nominal sixteen: a class
     * that has run eight of sixteen must not show every student at 50%.
     */
    public function persenKehadiran(Mahasiswa $mahasiswa, KelasKuliah $kelas): ?float
    {
        $terlaksana = $kelas->pertemuan()->where('is_terlaksana', true)->pluck('id');

        if ($terlaksana->isEmpty()) {
            return null;
        }

        $hadir = Presensi::whereIn('pertemuan_kelas_id', $terlaksana)
            ->where('mahasiswa_id', $mahasiswa->id)
            ->get()
            ->filter(fn (Presensi $p): bool => $p->status->countsAsPresent())
            ->count();

        return round($hadir / $terlaksana->count() * 100, 1);
    }

    /**
     * Whether the student has met the minimum attendance to sit the final exam.
     *
     * A class with no meetings held yet cannot disqualify anyone, so the answer
     * there is yes.
     */
    public function layakUas(Mahasiswa $mahasiswa, KelasKuliah $kelas): bool
    {
        $persen = $this->persenKehadiran($mahasiswa, $kelas);

        return $persen === null
            || $persen >= (float) config('academic.attendance.min_percent_for_final_exam');
    }

    /**
     * How many students in each class are below the attendance threshold.
     *
     * The class-listing screen needs one number per class, and asking
     * rekapKelas() for it is how that screen came to take eighteen seconds on a
     * five-thousand-student campus: per class it ran an enrolment subquery over
     * every study-plan row, then pulled every attendance record for the class
     * into PHP just to count them.
     *
     * Three aggregate queries instead, regardless of how many classes a
     * lecturer teaches. The counting happens in the database, which is what a
     * database is for.
     *
     * @param Collection<int, KelasKuliah>|iterable<KelasKuliah> $kelas
     * @return array<int, int> kelas id => number of students at risk
     */
    public function rawanAbsensi(iterable $kelas, ?float $minimum = null): array
    {
        $ids = collect($kelas)->pluck('id')->all();

        if ($ids === []) {
            return [];
        }

        $minimum ??= (float) config('academic.attendance.min_percent_for_final_exam');

        // Meetings actually held. A class that has not met yet cannot put
        // anybody at risk.
        $terlaksana = PertemuanKelas::query()
            ->whereIn('kelas_kuliah_id', $ids)
            ->where('is_terlaksana', true)
            ->groupBy('kelas_kuliah_id')
            ->pluck(DB::raw('COUNT(*)'), 'kelas_kuliah_id');

        // Everyone enrolled through an approved study plan, per class.
        $terdaftar = DB::table('krs_detail')
            ->join('krs', 'krs.id', '=', 'krs_detail.krs_id')
            ->whereIn('krs_detail.kelas_kuliah_id', $ids)
            ->where('krs.status', KrsStatus::Disetujui->value)
            ->select('krs_detail.kelas_kuliah_id', 'krs.mahasiswa_id')
            ->get()
            ->groupBy('kelas_kuliah_id');

        // Attendances that count as present, per class per student.
        $hadir = DB::table('presensi')
            ->join('pertemuan_kelas', 'pertemuan_kelas.id', '=', 'presensi.pertemuan_kelas_id')
            ->whereIn('pertemuan_kelas.kelas_kuliah_id', $ids)
            ->where('pertemuan_kelas.is_terlaksana', true)
            ->where('presensi.status', '!=', AttendanceStatus::Alpa->value)
            ->groupBy('pertemuan_kelas.kelas_kuliah_id', 'presensi.mahasiswa_id')
            ->select(
                'pertemuan_kelas.kelas_kuliah_id',
                'presensi.mahasiswa_id',
                DB::raw('COUNT(*) as jumlah'),
            )
            ->get()
            ->groupBy('kelas_kuliah_id')
            ->map(fn ($baris) => $baris->pluck('jumlah', 'mahasiswa_id'));

        $hasil = [];

        foreach ($ids as $id) {
            $sesi = (int) ($terlaksana[$id] ?? 0);

            if ($sesi === 0) {
                $hasil[$id] = 0;

                continue;
            }

            $perKelas = $hadir->get($id) ?? collect();

            // A student with no attendance row at all sits at 0% — absent is
            // absent, whether it was recorded or never entered.
            $hasil[$id] = ($terdaftar->get($id) ?? collect())
                ->filter(fn ($baris): bool => ((int) ($perKelas[$baris->mahasiswa_id] ?? 0)) / $sesi * 100 < $minimum)
                ->count();
        }

        return $hasil;
    }

    /**
     * Attendance percentages for the whole class, keyed by student id.
     *
     * Fine for one class on the attendance grid; do not call it in a loop —
     * see rawanAbsensi().
     *
     * @return array<int, float|null>
     */
    public function rekapKelas(KelasKuliah $kelas): array
    {
        $terlaksana = $kelas->pertemuan()->where('is_terlaksana', true)->pluck('id');
        $peserta = $this->pesertaKelas($kelas);

        if ($terlaksana->isEmpty()) {
            return $peserta->mapWithKeys(fn (Mahasiswa $m): array => [$m->id => null])->all();
        }

        $hadir = Presensi::whereIn('pertemuan_kelas_id', $terlaksana)
            ->whereIn('mahasiswa_id', $peserta->pluck('id'))
            ->get()
            ->groupBy('mahasiswa_id')
            ->map(fn (Collection $baris): int => $baris->filter(
                fn (Presensi $p): bool => $p->status->countsAsPresent(),
            )->count());

        return $peserta
            ->mapWithKeys(fn (Mahasiswa $m): array => [
                $m->id => round(($hadir[$m->id] ?? 0) / $terlaksana->count() * 100, 1),
            ])
            ->all();
    }

    /**
     * Students enrolled in the class through an approved study plan.
     *
     * @return Collection<int, Mahasiswa>
     */
    public function pesertaKelas(KelasKuliah $kelas): Collection
    {
        return Mahasiswa::query()
            ->whereHas(
                'krs.detail',
                fn ($query) => $query->where('kelas_kuliah_id', $kelas->id),
            )
            ->orderBy('nim')
            ->get();
    }
}

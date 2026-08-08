<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Enums\AttendanceStatus;
use App\Enums\GradeLetter;
use App\Enums\KrsStatus;
use App\Enums\SemesterType;
use App\Enums\StudentStatus;
use App\Models\Akademik\KelasKuliah;
use App\Models\Akademik\Krs;
use App\Models\Akademik\KrsDetail;
use App\Models\Akademik\Nilai;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Kemahasiswaan\StatusMahasiswa;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Walks every student through every term: study plan, grades, term GPA, and
 * attendance.
 *
 * Closed terms get finalised grades so the active term has a real IPS to
 * derive its credit ceiling from — without that history the KRS screen would
 * be demonstrating a rule it never actually applies. The active term is left
 * deliberately mixed (approved / submitted / draft) so the advisor's approval
 * queue is not empty on first login.
 */
class RiwayatAkademikSeeder extends Seeder
{
    public function run(): void
    {
        $terms = TahunAkademik::orderBy('kode')->get();
        $mahasiswa = Mahasiswa::with('prodi')->get();

        foreach ($mahasiswa as $index => $student) {
            $sksKumulatif = 0;
            $mutuKumulatif = 0.0;

            foreach ($terms as $term) {
                $semesterKe = $this->semesterKe($student->angkatan, $term);

                if ($semesterKe < 1) {
                    continue; // not enrolled yet
                }

                if ($student->status === StudentStatus::Cuti && $term->is_active) {
                    $this->recordStatus($student, $term, $semesterKe, StudentStatus::Cuti, 0, 0.0, $sksKumulatif, $mutuKumulatif);

                    continue;
                }

                $kelas = $this->kelasUntuk($student, $term, $semesterKe);

                if ($kelas->isEmpty()) {
                    continue;
                }

                $term->is_active
                    ? $this->seedTermBerjalan($student, $term, $semesterKe, $kelas, $index, $sksKumulatif, $mutuKumulatif)
                    : $this->seedTermSelesai($student, $term, $semesterKe, $kelas, $sksKumulatif, $mutuKumulatif);
            }
        }
    }

    /**
     * Study semester the student is in during the given term, counting from
     * their intake year and the odd/even alternation.
     */
    private function semesterKe(int $angkatan, TahunAkademik $term): int
    {
        $tahunBerjalan = (int) $term->tahun_mulai - $angkatan;
        $offset = $term->semester === SemesterType::Ganjil ? 1 : 2;

        return $tahunBerjalan * 2 + $offset;
    }

    /**
     * Offerings this student should take. Curriculum data covers semesters 1–4,
     * so senior students are mapped onto the highest offering of matching
     * parity — enough for a demo, and flagged here so nobody mistakes it for a
     * business rule.
     *
     * @return Collection<int, KelasKuliah>
     */
    private function kelasUntuk(Mahasiswa $student, TahunAkademik $term, int $semesterKe): Collection
    {
        $ditawarkan = $term->semester === SemesterType::Ganjil ? [1, 3] : [2, 4];
        $target = in_array($semesterKe, $ditawarkan, true) ? $semesterKe : max($ditawarkan);

        $kelas = KelasKuliah::query()
            ->with('mataKuliah.prasyarat')
            ->where('tahun_akademik_id', $term->id)
            ->where('prodi_id', $student->prodi_id)
            ->whereHas(
                'mataKuliah.kurikulum',
                fn ($q) => $q->where('kurikulum_mata_kuliah.semester', $target),
            )
            ->get();

        // Seeder menulis langsung ke tabel, melewati KrsService, sehingga
        // aturan prasyarat tidak ikut ditegakkan. Tanpa penyaringan ini data
        // demo memuat rencana studi yang melanggar prasyaratnya sendiri —
        // persis kontradiksi yang paling cepat terlihat di layar KRS.
        return $kelas->filter(
            fn (KelasKuliah $offering): bool => $this->prasyaratTerpenuhi($student, $offering),
        )->values();
    }

    /** Apakah seluruh prasyarat mata kuliah ini sudah dilulusi mahasiswa. */
    private function prasyaratTerpenuhi(Mahasiswa $student, KelasKuliah $offering): bool
    {
        $prasyarat = $offering->mataKuliah->prasyarat;

        if ($prasyarat->isEmpty()) {
            return true;
        }

        $lulus = Nilai::query()
            ->where('mahasiswa_id', $student->id)
            ->where('is_final', true)
            ->whereHas(
                'kelasKuliah',
                fn ($q) => $q->whereIn('mata_kuliah_id', $prasyarat->pluck('id')),
            )
            ->pluck('kelas_kuliah_id');

        return $lulus->count() >= $prasyarat->count();
    }

    /** A closed term: approved plan, finalised grades, frozen GPA. */
    private function seedTermSelesai(
        Mahasiswa $student,
        TahunAkademik $term,
        int $semesterKe,
        Collection $kelas,
        int &$sksKumulatif,
        float &$mutuKumulatif,
    ): void {
        $krs = Krs::create([
            'mahasiswa_id' => $student->id,
            'tahun_akademik_id' => $term->id,
            'semester_ke' => $semesterKe,
            'status' => KrsStatus::Disetujui,
            'total_sks' => $kelas->sum('sks'),
            'batas_sks' => 24,
            'ips_acuan' => $sksKumulatif > 0 ? round($mutuKumulatif / $sksKumulatif, 2) : null,
            'diajukan_at' => $term->tanggal_mulai,
            'disetujui_at' => $term->tanggal_mulai,
            'disetujui_by_dosen_id' => $student->dosen_wali_id,
        ]);

        $sksSemester = 0;
        $mutuSemester = 0.0;

        foreach ($kelas as $offering) {
            $detail = KrsDetail::create([
                'krs_id' => $krs->id,
                'kelas_kuliah_id' => $offering->id,
                'sks' => $offering->sks,
            ]);

            $angka = $this->skorAkhir();
            $huruf = GradeLetter::fromScore($angka);

            $this->seedNilaiKomponen($offering, $detail->id, $angka);

            Nilai::create([
                'krs_detail_id' => $detail->id,
                'kelas_kuliah_id' => $offering->id,
                'mahasiswa_id' => $student->id,
                'nilai_angka' => $angka,
                'nilai_huruf' => $huruf,
                'bobot' => $huruf->weight(),
                'is_final' => true,
                'finalized_at' => $term->tanggal_selesai,
                'finalized_by_dosen_id' => $offering->dosenPengampu()->first()?->id,
            ]);

            $sksSemester += $offering->sks;
            $mutuSemester += $huruf->weight() * $offering->sks;

            $offering->increment('terisi');
        }

        $sksKumulatif += $sksSemester;
        $mutuKumulatif += $mutuSemester;

        $this->recordStatus(
            $student,
            $term,
            $semesterKe,
            StudentStatus::Aktif,
            $sksSemester,
            $sksSemester > 0 ? round($mutuSemester / $sksSemester, 2) : 0.0,
            $sksKumulatif,
            $mutuKumulatif,
            final: true,
        );

        $kelas->each(fn (KelasKuliah $k) => $k->update(['status_nilai' => 'final']));
    }

    /**
     * The active term: plans in three different states, attendance filled in
     * for meetings that have already been held, and no final grades yet.
     */
    private function seedTermBerjalan(
        Mahasiswa $student,
        TahunAkademik $term,
        int $semesterKe,
        Collection $kelas,
        int $index,
        int $sksKumulatif,
        float $mutuKumulatif,
    ): void {
        $ips = $sksKumulatif > 0 ? round($mutuKumulatif / $sksKumulatif, 2) : null;

        $status = match ($index % 5) {
            0, 1, 2 => KrsStatus::Disetujui,
            3 => KrsStatus::Diajukan,
            default => KrsStatus::Draft,
        };

        $krs = Krs::create([
            'mahasiswa_id' => $student->id,
            'tahun_akademik_id' => $term->id,
            'semester_ke' => $semesterKe,
            'status' => $status,
            'total_sks' => $kelas->sum('sks'),
            'batas_sks' => $this->batasSks($ips),
            'ips_acuan' => $ips,
            'diajukan_at' => $status === KrsStatus::Draft ? null : now()->subDays(5),
            'disetujui_at' => $status === KrsStatus::Disetujui ? now()->subDays(3) : null,
            'disetujui_by_dosen_id' => $status === KrsStatus::Disetujui ? $student->dosen_wali_id : null,
        ]);

        foreach ($kelas as $offering) {
            $detail = KrsDetail::create([
                'krs_id' => $krs->id,
                'kelas_kuliah_id' => $offering->id,
                'sks' => $offering->sks,
            ]);

            $offering->increment('terisi');

            if ($status === KrsStatus::Disetujui) {
                $this->seedPresensi($offering, $student->id);
            }
        }

        $this->recordStatus(
            $student,
            $term,
            $semesterKe,
            $student->status,
            0,
            0.0,
            $sksKumulatif,
            $mutuKumulatif,
        );
    }

    /** Credit ceiling from the configured IPS matrix. */
    private function batasSks(?float $ips): int
    {
        if ($ips === null) {
            return (int) config('academic.krs.default_credits');
        }

        foreach (config('academic.krs.credit_limits') as $row) {
            if ($ips >= (float) $row['min_ips']) {
                return (int) $row['credits'];
            }
        }

        return (int) config('academic.krs.default_credits');
    }

    /**
     * Component scores that reconstruct the given final score, so the grade
     * entry screen shows numbers that actually add up to the letter awarded.
     */
    private function seedNilaiKomponen(KelasKuliah $kelas, int $krsDetailId, float $target): void
    {
        $rows = [];

        foreach ($kelas->komponenNilai as $komponen) {
            $rows[] = [
                'komponen_nilai_id' => $komponen->id,
                'krs_detail_id' => $krsDetailId,
                'nilai' => min(100, max(0, round($target + fake()->randomFloat(1, -6, 6), 2))),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($rows !== []) {
            DB::table('nilai_komponen')->insert($rows);
        }
    }

    private function seedPresensi(KelasKuliah $kelas, int $mahasiswaId): void
    {
        $rows = [];

        foreach ($kelas->pertemuan()->where('is_terlaksana', true)->get() as $pertemuan) {
            $rows[] = [
                'uuid' => (string) Str::uuid(),
                'pertemuan_kelas_id' => $pertemuan->id,
                'mahasiswa_id' => $mahasiswaId,
                'status' => $this->markKehadiran()->value,
                'waktu_absen' => $pertemuan->tanggal,
                'sumber' => 'dosen',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($rows !== []) {
            DB::table('presensi')->insert($rows);
        }
    }

    /** Mostly present, with enough absence to exercise the 75% rule. */
    private function markKehadiran(): AttendanceStatus
    {
        return match (true) {
            fake()->boolean(84) => AttendanceStatus::Hadir,
            fake()->boolean(50) => AttendanceStatus::Izin,
            fake()->boolean(50) => AttendanceStatus::Sakit,
            default => AttendanceStatus::Alpa,
        };
    }

    /** Weighted toward passing grades, as a real cohort is. */
    private function skorAkhir(): float
    {
        return match (true) {
            fake()->boolean(30) => fake()->randomFloat(2, 80, 96),
            fake()->boolean(45) => fake()->randomFloat(2, 70, 79.9),
            fake()->boolean(70) => fake()->randomFloat(2, 55, 69.9),
            default => fake()->randomFloat(2, 40, 54.9),
        };
    }

    private function recordStatus(
        Mahasiswa $student,
        TahunAkademik $term,
        int $semesterKe,
        StudentStatus $status,
        int $sksSemester,
        float $ips,
        int $sksKumulatif,
        float $mutuKumulatif,
        bool $final = false,
    ): void {
        StatusMahasiswa::updateOrCreate(
            ['mahasiswa_id' => $student->id, 'tahun_akademik_id' => $term->id],
            [
                'status' => $status,
                'semester_ke' => $semesterKe,
                'sks_semester' => $sksSemester,
                'sks_kumulatif' => $sksKumulatif,
                'ips' => $ips,
                'ipk' => $sksKumulatif > 0 ? round($mutuKumulatif / $sksKumulatif, 2) : 0.0,
                'is_final' => $final,
                'finalized_at' => $final ? $term->tanggal_selesai : null,
            ],
        );
    }
}

<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Enums\SemesterType;
use App\Models\Akademik\JadwalKuliah;
use App\Models\Akademik\KelasKuliah;
use App\Models\Akademik\KomponenNilai;
use App\Models\Akademik\Kurikulum;
use App\Models\Akademik\PertemuanKelas;
use App\Models\Akademik\Ruang;
use App\Models\Akademik\TahunAkademik;
use App\Models\Sdm\Dosen;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Class offerings, teaching assignments, weekly schedules and meetings.
 *
 * Rooms are allocated from a single global slot counter so no two classes ever
 * land on the same (room, day, hour) triple — the demo campus must not open
 * with the clash detector already lit up red.
 */
class PerkuliahanSeeder extends Seeder
{
    /** Lecture start times, 100-minute blocks. */
    private const JAM = ['07:30', '09:20', '11:10', '13:00', '15:00'];

    private int $slot = 0;

    public function run(): void
    {
        $ruangKelas = Ruang::where('jenis', 'kelas')->get();
        $ruangLab = Ruang::where('jenis', 'laboratorium')->get();

        foreach (TahunAkademik::orderBy('kode')->get() as $term) {
            $this->seedTerm($term, $ruangKelas, $ruangLab);
        }
    }

    /**
     * @param Collection<int, Ruang> $ruangKelas
     * @param Collection<int, Ruang> $ruangLab
     */
    private function seedTerm(TahunAkademik $term, Collection $ruangKelas, Collection $ruangLab): void
    {
        // Odd terms offer odd-numbered curriculum semesters, even terms the
        // even ones — the same rule a real registrar follows.
        $semesterOffered = $term->semester === SemesterType::Ganjil ? [1, 3] : [2, 4];

        foreach (Kurikulum::with(['prodi', 'mataKuliah'])->where('is_active', true)->get() as $kurikulum) {
            $pengampuProdi = Dosen::where('prodi_id', $kurikulum->prodi_id)->get();

            if ($pengampuProdi->isEmpty()) {
                continue;
            }

            $mataKuliah = $kurikulum->mataKuliah
                ->filter(fn ($mk) => in_array((int) $mk->pivot->semester, $semesterOffered, true));

            foreach ($mataKuliah->values() as $index => $mk) {
                $kelas = KelasKuliah::create([
                    'tahun_akademik_id' => $term->id,
                    'mata_kuliah_id' => $mk->id,
                    'prodi_id' => $kurikulum->prodi_id,
                    'kode' => 'A',
                    'kuota' => 40,
                    'terisi' => 0,
                    'sks' => $mk->sks,
                    'mode' => $index % 7 === 0 ? 'daring' : 'tatap_muka',

                    // IKU 7 evidence: roughly a third of offerings run a
                    // collaborative method.
                    'is_case_method' => $index % 3 === 0,
                    'is_team_based_project' => $index % 4 === 0,

                    'status_nilai' => 'belum',
                ]);

                $this->attachDosen($kelas, $pengampuProdi, $index);
                $this->attachJadwal($kelas, $mk->sks_praktik > 0 ? $ruangLab : $ruangKelas);
                $this->attachKomponenNilai($kelas);

                if ($term->is_active) {
                    $this->attachPertemuan($kelas, $term);
                }
            }
        }
    }

    /** @param Collection<int, Dosen> $pengampuProdi */
    private function attachDosen(KelasKuliah $kelas, Collection $pengampuProdi, int $index): void
    {
        $pengampu = $pengampuProdi[$index % $pengampuProdi->count()];

        $kelas->dosen()->attach($pengampu->id, [
            'peran' => 'pengampu',
            'porsi_sks' => $kelas->sks,
        ]);

        // Every fifth class is co-taught by a practitioner — the IKU 4 source.
        if ($index % 5 === 0) {
            $praktisi = Dosen::where('is_praktisi', true)->first();

            if ($praktisi !== null && $praktisi->id !== $pengampu->id) {
                $kelas->dosen()->attach($praktisi->id, [
                    'peran' => 'praktisi',
                    'porsi_sks' => 1,
                    'praktisi_instansi' => $praktisi->praktisi_instansi,
                ]);
            }
        }
    }

    /**
     * Allocates a (day, hour, room) triple from a single global counter.
     *
     * The counter walks the 25 day/hour slots first and only then moves to the
     * next room, so consecutive offerings land at different times. Filling
     * rooms first would put a whole cohort's classes in the same hour — no
     * room clash, but every student double-booked.
     *
     * @param Collection<int, Ruang> $ruangPool
     */
    private function attachJadwal(KelasKuliah $kelas, Collection $ruangPool): void
    {
        $blokPerHari = 5 * count(self::JAM);

        $blok = $this->slot % $blokPerHari;
        $ruang = $ruangPool[intdiv($this->slot, $blokPerHari) % $ruangPool->count()];

        $hari = 1 + ($blok % 5);
        $jamMulai = self::JAM[intdiv($blok, 5) % count(self::JAM)];

        JadwalKuliah::create([
            'kelas_kuliah_id' => $kelas->id,
            'ruang_id' => $kelas->mode === 'daring' ? null : $ruang->id,
            'hari' => $hari,
            'jam_mulai' => $jamMulai.':00',
            'jam_selesai' => Carbon::createFromFormat('H:i', $jamMulai)
                ->addMinutes(50 * $kelas->sks)
                ->format('H:i:s'),
        ]);

        $this->slot++;
    }

    private function attachKomponenNilai(KelasKuliah $kelas): void
    {
        foreach (config('academic.grading.default_components') as $urutan => $komponen) {
            KomponenNilai::create([
                'kelas_kuliah_id' => $kelas->id,
                'nama' => $komponen['name'],
                'bobot' => $komponen['weight'],
                'urutan' => $urutan,
            ]);
        }
    }

    /**
     * 16 weekly meetings. Meetings whose date has passed are marked as held so
     * the attendance grid opens with a believable amount of history.
     */
    private function attachPertemuan(KelasKuliah $kelas, TahunAkademik $term): void
    {
        $jadwal = $kelas->jadwal()->first();
        $pengampu = $kelas->dosenPengampu()->first();

        $mulai = Carbon::parse($term->tanggal_mulai)->startOfWeek()->addDays(($jadwal?->hari ?? 1) - 1);

        for ($ke = 1; $ke <= (int) config('academic.attendance.meetings_per_term'); $ke++) {
            $tanggal = (clone $mulai)->addWeeks($ke - 1);

            PertemuanKelas::create([
                'kelas_kuliah_id' => $kelas->id,
                'dosen_id' => $pengampu?->id,
                'pertemuan_ke' => $ke,
                'tanggal' => $tanggal,
                'jam_mulai' => $jadwal?->jam_mulai,
                'jam_selesai' => $jadwal?->jam_selesai,
                'topik' => 'Pertemuan '.$ke,
                'metode' => $kelas->mode === 'daring' ? 'daring' : 'tatap_muka',
                'is_terlaksana' => $tanggal->isPast(),
            ]);
        }
    }
}

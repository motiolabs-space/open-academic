<?php

declare(strict_types=1);

namespace App\Services\Akademik;

use App\DTOs\Akademik\BentrokJadwal;
use App\Exceptions\AturanAkademikException;
use App\Models\Akademik\JadwalKuliah;
use App\Models\Akademik\KelasKuliah;
use App\Models\Akademik\Ruang;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Weekly timetable slots, and the clashes that make one unteachable.
 *
 * A scheduling screen without clash detection is a form. The failures it is
 * supposed to catch do not announce themselves: nothing errors when two classes
 * are put in one room, or when a whole cohort's compulsory courses land in the
 * same hour. Everyone finds out in week one, when the timetable is already
 * printed and the students are already enrolled.
 *
 * That last one is not hypothetical — the demo seeder produced exactly it in an
 * earlier release, filling rooms before hours so an entire intake collided.
 */
class JadwalService
{
    /**
     * Every reason the proposed slot does not work.
     *
     * Returns all of them rather than the first: a registrar moving a class
     * needs to see the whole picture, not fix one clash and discover the next
     * on the following attempt.
     *
     * @return Collection<int, BentrokJadwal>
     */
    public function periksaBentrok(
        KelasKuliah $kelas,
        int $hari,
        string $jamMulai,
        string $jamSelesai,
        ?int $ruangId = null,
        ?int $abaikanJadwalId = null,
    ): Collection {
        $bentrok = collect();

        // Slots that share a wall-clock window on the same weekday. Touching
        // endpoints do not overlap: a class ending at 10:00 and one starting at
        // 10:00 are consecutive, not simultaneous.
        $beririsan = JadwalKuliah::query()
            ->with(['kelasKuliah.mataKuliah', 'kelasKuliah.dosen', 'ruang'])
            ->where('hari', $hari)
            ->where('jam_mulai', '<', $jamSelesai)
            ->where('jam_selesai', '>', $jamMulai)
            ->when($abaikanJadwalId !== null, fn ($q) => $q->whereKeyNot($abaikanJadwalId))
            ->whereHas('kelasKuliah', fn ($q) => $q->where('tahun_akademik_id', $kelas->tahun_akademik_id))
            ->get()
            ->reject(fn (JadwalKuliah $j) => $j->kelas_kuliah_id === $kelas->id);

        $this->periksaRuang($bentrok, $beririsan, $ruangId, $kelas);
        $this->periksaDosen($bentrok, $beririsan, $kelas);
        $this->periksaKohor($bentrok, $beririsan, $kelas);
        $this->periksaKapasitas($bentrok, $ruangId, $kelas);

        return $bentrok;
    }

    /**
     * Creates the slot, refusing only what is physically impossible.
     *
     * Warnings are returned to the caller instead of thrown, so the screen can
     * show them and let a registrar decide.
     *
     * @return array{jadwal: JadwalKuliah, peringatan: Collection<int, BentrokJadwal>}
     */
    public function jadwalkan(
        KelasKuliah $kelas,
        int $hari,
        string $jamMulai,
        string $jamSelesai,
        ?int $ruangId = null,
    ): array {
        if ($jamSelesai <= $jamMulai) {
            throw new AturanAkademikException('Jam selesai harus setelah jam mulai.');
        }

        $bentrok = $this->periksaBentrok($kelas, $hari, $jamMulai, $jamSelesai, $ruangId);
        $penghalang = $bentrok->filter(fn (BentrokJadwal $b): bool => $b->menghalangi);

        if ($penghalang->isNotEmpty()) {
            throw new AturanAkademikException(
                'Jadwal tidak dapat disimpan: '.$penghalang->pluck('pesan')->implode('; ').'.',
            );
        }

        $jadwal = JadwalKuliah::create([
            'kelas_kuliah_id' => $kelas->id,
            'ruang_id' => $ruangId,
            'hari' => $hari,
            'jam_mulai' => $jamMulai,
            'jam_selesai' => $jamSelesai,
        ]);

        return ['jadwal' => $jadwal, 'peringatan' => $bentrok->reject(fn (BentrokJadwal $b): bool => $b->menghalangi)];
    }

    public function hapus(JadwalKuliah $jadwal): void
    {
        $jadwal->delete();
    }

    /**
     * @param Collection<int, BentrokJadwal> $bentrok
     * @param Collection<int, JadwalKuliah> $beririsan
     */
    private function periksaRuang(Collection $bentrok, Collection $beririsan, ?int $ruangId, KelasKuliah $kelas): void
    {
        if ($ruangId === null) {
            return;
        }

        foreach ($beririsan->where('ruang_id', $ruangId) as $lain) {
            $bentrok->push(BentrokJadwal::ruang(sprintf(
                'ruang %s sudah dipakai %s pada jam yang sama',
                $lain->ruang?->kode ?? '—',
                $lain->kelasKuliah->namaLengkap(),
            )));
        }
    }

    /**
     * @param Collection<int, BentrokJadwal> $bentrok
     * @param Collection<int, JadwalKuliah> $beririsan
     */
    private function periksaDosen(Collection $bentrok, Collection $beririsan, KelasKuliah $kelas): void
    {
        $pengampu = $kelas->dosen->pluck('nama', 'id');

        if ($pengampu->isEmpty()) {
            return;
        }

        foreach ($beririsan as $lain) {
            $tabrakan = $lain->kelasKuliah->dosen->pluck('id')->intersect($pengampu->keys());

            foreach ($tabrakan as $dosenId) {
                $bentrok->push(BentrokJadwal::dosen(sprintf(
                    '%s sudah mengajar %s pada jam yang sama',
                    $pengampu[$dosenId],
                    $lain->kelasKuliah->namaLengkap(),
                )));
            }
        }
    }

    /**
     * Compulsory courses of the same intake landing in the same hour.
     *
     * Not blocking: electives overlapping is ordinary, and a campus may
     * deliberately run parallel streams. But a whole cohort unable to take two
     * of its own required courses is the failure nobody notices until KRS week,
     * so it is surfaced loudly.
     *
     * @param Collection<int, BentrokJadwal> $bentrok
     * @param Collection<int, JadwalKuliah> $beririsan
     */
    private function periksaKohor(Collection $bentrok, Collection $beririsan, KelasKuliah $kelas): void
    {
        $semester = $this->semesterKurikulum($kelas);

        if ($semester === null) {
            return;
        }

        foreach ($beririsan as $lain) {
            if ($lain->kelasKuliah->prodi_id !== $kelas->prodi_id) {
                continue;
            }

            if ($this->semesterKurikulum($lain->kelasKuliah) !== $semester) {
                continue;
            }

            $bentrok->push(BentrokJadwal::kohor(sprintf(
                '%s berada pada semester kurikulum yang sama (semester %d) dan waktunya beririsan — '
                    .'mahasiswa angkatan itu tidak dapat mengambil keduanya',
                $lain->kelasKuliah->namaLengkap(),
                $semester,
            )));
        }
    }

    /** @param Collection<int, BentrokJadwal> $bentrok */
    private function periksaKapasitas(Collection $bentrok, ?int $ruangId, KelasKuliah $kelas): void
    {
        if ($ruangId === null) {
            return;
        }

        $ruang = Ruang::find($ruangId);

        if ($ruang === null || (int) $ruang->kapasitas === 0) {
            return;
        }

        if ((int) $kelas->kuota > (int) $ruang->kapasitas) {
            $bentrok->push(BentrokJadwal::kapasitas(sprintf(
                'kuota kelas (%d) melebihi kapasitas ruang %s (%d)',
                $kelas->kuota,
                $ruang->kode,
                $ruang->kapasitas,
            )));
        }
    }

    /**
     * The curriculum semester this offering's course sits in.
     *
     * Read from the pivot rather than stored on the class: the same course can
     * be positioned differently in two curriculum versions, and the answer that
     * matters is the one for the programme's active version.
     */
    private function semesterKurikulum(KelasKuliah $kelas): ?int
    {
        $semester = DB::table('kurikulum_mata_kuliah')
            ->join('kurikulum', 'kurikulum.id', '=', 'kurikulum_mata_kuliah.kurikulum_id')
            ->where('kurikulum_mata_kuliah.mata_kuliah_id', $kelas->mata_kuliah_id)
            ->where('kurikulum.prodi_id', $kelas->prodi_id)
            ->where('kurikulum.is_active', true)
            ->value('kurikulum_mata_kuliah.semester');

        return $semester === null ? null : (int) $semester;
    }
}

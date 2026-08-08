<?php

declare(strict_types=1);

namespace App\Services\Akademik;

use App\Exceptions\AturanAkademikException;
use App\Models\Akademik\JadwalKuliah;
use App\Models\Akademik\KelasKuliah;
use App\Models\Akademik\MataKuliah;
use App\Models\Akademik\TahunAkademik;
use App\Models\Sdm\Dosen;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Opening course offerings for a term.
 *
 * This is the step that turns a curriculum into something a student can
 * actually enrol in. Without it the KRS catalogue is empty and the semester
 * cannot start, which is why it was the one dead menu item left in the portal.
 */
class KelasKuliahService
{
    public function __construct(private readonly JadwalService $jadwal) {}

    /**
     * Opens one offering.
     *
     * Credits are snapshotted from the course rather than referenced, because a
     * curriculum revision that changes a course from 3 to 4 credits must not
     * retroactively change what students already enrolled in were graded on.
     */
    public function buka(TahunAkademik $term, MataKuliah $mataKuliah, string $kode, int $kuota, array $opsi = []): KelasKuliah
    {
        if ($term->is_locked) {
            throw new AturanAkademikException("Semester {$term->nama} sudah dikunci; kelas baru tidak dapat dibuka.");
        }

        $bentrok = KelasKuliah::withTrashed()
            ->where('tahun_akademik_id', $term->id)
            ->where('mata_kuliah_id', $mataKuliah->id)
            ->where('kode', $kode)
            ->exists();

        if ($bentrok) {
            throw new AturanAkademikException(
                "Kelas {$kode} untuk {$mataKuliah->kode} sudah ada pada {$term->nama}.",
            );
        }

        return KelasKuliah::create([
            'tahun_akademik_id' => $term->id,
            'mata_kuliah_id' => $mataKuliah->id,
            'prodi_id' => $mataKuliah->prodi_id,
            'kode' => $kode,
            'kuota' => $kuota,
            'terisi' => 0,

            // Snapshot, not a reference — see the docblock.
            'sks' => $mataKuliah->sks,

            'mode' => $opsi['mode'] ?? 'tatap_muka',
            'is_case_method' => (bool) ($opsi['is_case_method'] ?? false),
            'is_team_based_project' => (bool) ($opsi['is_team_based_project'] ?? false),
            'status_nilai' => 'belum',
        ]);
    }

    /**
     * Opens several parallel offerings of one course at once.
     *
     * A first-year compulsory course needs eight sections; creating them one at
     * a time is the kind of task that makes an operator open the database
     * instead.
     *
     * @return Collection<int, KelasKuliah>
     */
    public function bukaParalel(TahunAkademik $term, MataKuliah $mataKuliah, int $jumlah, int $kuota, array $opsi = []): Collection
    {
        if ($jumlah < 1 || $jumlah > 26) {
            throw new AturanAkademikException('Jumlah kelas paralel harus antara 1 dan 26.');
        }

        $terpakai = KelasKuliah::withTrashed()
            ->where('tahun_akademik_id', $term->id)
            ->where('mata_kuliah_id', $mataKuliah->id)
            ->pluck('kode')
            ->all();

        $dibuat = collect();
        $huruf = range('A', 'Z');
        $i = 0;

        while ($dibuat->count() < $jumlah && $i < 26) {
            $kode = $huruf[$i++];

            if (in_array($kode, $terpakai, true)) {
                continue;
            }

            $dibuat->push($this->buka($term, $mataKuliah, $kode, $kuota, $opsi));
        }

        if ($dibuat->count() < $jumlah) {
            throw new AturanAkademikException(
                'Kode kelas A–Z sudah habis terpakai untuk mata kuliah ini pada semester tersebut.',
            );
        }

        return $dibuat;
    }

    /**
     * Adjusts an offering.
     *
     * The quota floor is the one rule with teeth: lowering it below the number
     * already enrolled would leave students holding a seat the class no longer
     * admits, and nothing downstream checks for that — the KRS seat claim only
     * looks at the ceiling when adding.
     */
    public function perbarui(KelasKuliah $kelas, array $data): KelasKuliah
    {
        if (isset($data['kuota']) && (int) $data['kuota'] < (int) $kelas->terisi) {
            throw new AturanAkademikException(sprintf(
                'Kuota tidak dapat diturunkan menjadi %d — sudah ada %d mahasiswa terdaftar. '
                    .'Keluarkan mahasiswa dari kelas lebih dulu bila memang perlu.',
                $data['kuota'],
                $kelas->terisi,
            ));
        }

        $kelas->update(array_intersect_key($data, array_flip([
            'kuota', 'mode', 'is_case_method', 'is_team_based_project', 'nama',
        ])));

        return $kelas->refresh();
    }

    /**
     * Closes an offering.
     *
     * Refused once anybody has enrolled: deleting the class would strand study
     * plans that point at it, and any grade already entered would lose the
     * course it belongs to.
     */
    public function tutup(KelasKuliah $kelas): void
    {
        if ($kelas->krsDetail()->exists()) {
            throw new AturanAkademikException(
                'Kelas ini sudah diambil mahasiswa dan tidak dapat dihapus. '
                    .'Kosongkan kuotanya agar tidak dipilih lagi bila kelas tidak jadi berjalan.',
            );
        }

        DB::transaction(function () use ($kelas): void {
            $kelas->jadwal()->delete();
            $kelas->dosen()->detach();
            $kelas->delete();
        });
    }

    /**
     * Assigns a lecturer, with the timetable checked as part of the assignment.
     *
     * Assigning first and scheduling later is how a lecturer ends up in two
     * rooms at once: the clash check runs when the slot is created, and by then
     * the assignment already happened. So it runs here too.
     */
    public function tugaskanDosen(KelasKuliah $kelas, Dosen $dosen, string $peran = 'pengampu'): void
    {
        if (!$dosen->is_active) {
            throw new AturanAkademikException("{$dosen->nama} berstatus nonaktif dan tidak dapat ditugaskan mengajar.");
        }

        foreach ($kelas->jadwal as $slot) {
            $bentrok = $this->bentrokDosenPadaSlot($dosen, $kelas, $slot);

            if ($bentrok !== null) {
                throw new AturanAkademikException(
                    "{$dosen->nama} tidak dapat ditugaskan: {$bentrok}.",
                );
            }
        }

        $kelas->dosen()->syncWithoutDetaching([
            $dosen->id => [
                'peran' => $peran,

                // Carried onto the assignment so IKU 4 evidence survives even if
                // the lecturer's profile is edited later.
                'praktisi_instansi' => $peran === 'praktisi' ? $dosen->praktisi_instansi : null,
            ],
        ]);
    }

    public function lepasDosen(KelasKuliah $kelas, Dosen $dosen): void
    {
        $kelas->dosen()->detach($dosen->id);
    }

    /** The clash sentence, or null when the lecturer is free at that slot. */
    private function bentrokDosenPadaSlot(Dosen $dosen, KelasKuliah $kelas, JadwalKuliah $slot): ?string
    {
        $lain = JadwalKuliah::query()
            ->with('kelasKuliah.mataKuliah')
            ->where('hari', $slot->hari)
            ->where('jam_mulai', '<', $slot->jam_selesai)
            ->where('jam_selesai', '>', $slot->jam_mulai)
            ->whereHas('kelasKuliah', fn ($q) => $q
                ->where('tahun_akademik_id', $kelas->tahun_akademik_id)
                ->whereKeyNot($kelas->id)
                ->whereHas('dosen', fn ($d) => $d->where('dosen.id', $dosen->id)))
            ->first();

        return $lain === null
            ? null
            : sprintf('sudah mengajar %s pada jam yang sama', $lain->kelasKuliah->namaLengkap());
    }
}

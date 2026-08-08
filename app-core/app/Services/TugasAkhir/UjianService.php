<?php

declare(strict_types=1);

namespace App\Services\TugasAkhir;

use App\DTOs\Akademik\BentrokJadwal;
use App\Enums\HasilUjian;
use App\Enums\JenisUjian;
use App\Enums\PeranPenguji;
use App\Enums\StatusUjian;
use App\Enums\TugasAkhirStatus;
use App\Exceptions\AturanAkademikException;
use App\Models\Akademik\JadwalKuliah;
use App\Models\Sdm\Dosen;
use App\Models\TugasAkhir\Penguji;
use App\Models\TugasAkhir\TugasAkhir;
use App\Models\TugasAkhir\Ujian;
use App\Notifications\TugasAkhir\UjianDijadwalkan;
use App\Services\Notifikasi\Notifier;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Scheduling and running an examination.
 *
 * A defence collides with the ordinary timetable in a way class scheduling does
 * not: it happens on one specific date, in a room that is also booked weekly,
 * with lecturers who are also teaching that weekday. Checking only against other
 * defences finds none of that — the panel turns up to a room with a class in it.
 *
 * The blocking/non-blocking split is the same one JadwalService uses, and for
 * the same reason: refuse what is physically impossible, surface what is a
 * judgement the department is entitled to make. Software that blocks the second
 * kind gets worked around by scheduling outside it.
 */
class UjianService
{
    public function __construct(private readonly Notifier $notifier) {}

    /**
     * Every reason the proposed slot does not work.
     *
     * @param array<int, int> $pengujiIds
     * @return Collection<int, BentrokJadwal>
     */
    public function periksaBentrok(
        TugasAkhir $ta,
        string $tanggal,
        string $jamMulai,
        string $jamSelesai,
        ?int $ruangId,
        array $pengujiIds,
        ?int $abaikanUjianId = null,
    ): Collection {
        $bentrok = collect();

        $ujianLain = $this->ujianBeririsan($tanggal, $jamMulai, $jamSelesai, $abaikanUjianId);
        $kelasHariItu = $this->kelasBeririsan($tanggal, $jamMulai, $jamSelesai);

        $this->periksaRuang($bentrok, $ujianLain, $kelasHariItu, $ruangId);
        $this->periksaPenguji($bentrok, $ujianLain, $kelasHariItu, $pengujiIds);
        $this->periksaMahasiswa($bentrok, $ujianLain, $ta);

        return $bentrok;
    }

    /**
     * Schedules an examination and seats its panel.
     *
     * @param array<int, array{dosen_id: int, peran: PeranPenguji}> $panel
     * @return array{ujian: Ujian, peringatan: Collection<int, BentrokJadwal>}
     */
    public function jadwalkan(
        TugasAkhir $ta,
        JenisUjian $jenis,
        string $tanggal,
        string $jamMulai,
        string $jamSelesai,
        array $panel,
        ?int $ruangId = null,
    ): array {
        if ($jamSelesai <= $jamMulai) {
            throw new AturanAkademikException('Jam selesai harus setelah jam mulai.');
        }

        if ($ta->status !== TugasAkhirStatus::Dibimbing) {
            throw new AturanAkademikException(
                'Ujian hanya dapat dijadwalkan untuk tugas akhir yang sedang dibimbing. Status saat ini: '
                    .$ta->status->label().'.',
            );
        }

        if ($panel === []) {
            throw new AturanAkademikException('Susunan penguji tidak boleh kosong.');
        }

        $ta->loadMissing(['pembimbing', 'bimbingan']);

        $this->pastikanPanelSah($ta, $jenis, $panel);
        $this->pastikanSyaratSidang($ta, $jenis);

        $pengujiIds = array_map(static fn (array $k): int => (int) $k['dosen_id'], $panel);

        $bentrok = $this->periksaBentrok($ta, $tanggal, $jamMulai, $jamSelesai, $ruangId, $pengujiIds);
        $penghalang = $bentrok->filter(fn (BentrokJadwal $b): bool => $b->menghalangi);

        if ($penghalang->isNotEmpty()) {
            throw new AturanAkademikException(
                'Ujian tidak dapat dijadwalkan: '.$penghalang->pluck('pesan')->implode('; ').'.',
            );
        }

        $ujian = DB::transaction(function () use ($ta, $jenis, $tanggal, $jamMulai, $jamSelesai, $ruangId, $panel): Ujian {
            $ujian = Ujian::create([
                'tugas_akhir_id' => $ta->id,
                'jenis' => $jenis,
                'tanggal' => $tanggal,
                'jam_mulai' => $jamMulai,
                'jam_selesai' => $jamSelesai,
                'ruang_id' => $ruangId,
                'status' => StatusUjian::Dijadwalkan,
            ]);

            foreach ($panel as $kursi) {
                Penguji::create([
                    'tugas_akhir_ujian_id' => $ujian->id,
                    'dosen_id' => $kursi['dosen_id'],
                    'peran' => $kursi['peran'],
                ]);
            }

            $ta->recordActivity(
                'exam_scheduled',
                sprintf('%s dijadwalkan pada %s %s.', $jenis->label(), $tanggal, $jamMulai),
            );

            return $ujian;
        });

        $ujian = $ujian->refresh()->load('penguji.dosen', 'ruang', 'tugasAkhir.mahasiswa');

        /*
         * The student and every examiner, in one send.
         *
         * An examiner who learns of a panel seat on the morning itself is the
         * usual reason a defence gets moved — and the room booking that made it
         * possible has already been spent by then.
         */
        $this->notifier->kirimBanyak(
            [$ta->mahasiswa, ...$ujian->penguji->pluck('dosen')->all()],
            new UjianDijadwalkan($ujian),
        );

        return [
            'ujian' => $ujian,
            'peringatan' => $bentrok->reject(fn (BentrokJadwal $b): bool => $b->menghalangi),
        ];
    }

    /** One panel member's mark. */
    public function nilaiPenguji(Penguji $penguji, float $nilai, ?string $catatan = null): Penguji
    {
        if ($penguji->ujian->selesai()) {
            throw new AturanAkademikException(
                'Ujian ini sudah ditutup. Nilai penguji tidak dapat diubah setelah hasilnya dicatat.',
            );
        }

        if ($nilai < 0 || $nilai > 100) {
            throw new AturanAkademikException('Nilai ujian harus berada di antara 0 dan 100.');
        }

        $penguji->update(['nilai' => $nilai, 'catatan' => $catatan]);

        return $penguji->refresh();
    }

    /**
     * Records the panel's verdict and closes the session.
     *
     * The mark defaults to the panel average rather than being typed again —
     * a separately entered final mark that disagrees with the members' marks is
     * a dispute nobody can resolve months later.
     */
    public function catatHasil(
        Ujian $ujian,
        HasilUjian $hasil,
        ?float $nilai = null,
        ?string $batasRevisi = null,
        ?string $catatan = null,
    ): Ujian {
        if ($ujian->status !== StatusUjian::Dijadwalkan) {
            throw new AturanAkademikException(
                'Hanya ujian berstatus dijadwalkan yang dapat dicatat hasilnya. Status saat ini: '
                    .$ujian->status->label().'.',
            );
        }

        $ujian->loadMissing('penguji');

        $nilaiAkhir = $nilai ?? $ujian->rerataPenguji();

        if ($hasil->lulus() && $nilaiAkhir === null) {
            throw new AturanAkademikException(
                'Kelulusan ujian memerlukan nilai — belum ada satu pun penguji yang memberi nilai.',
            );
        }

        if ($hasil === HasilUjian::LulusRevisi && blank($batasRevisi)) {
            throw new AturanAkademikException(
                'Kelulusan dengan revisi wajib disertai batas waktu revisi. Tanpa tenggat, revisi '
                    .'menjadi kewajiban yang tidak pernah ditagih dan mahasiswa mengira dirinya sudah selesai.',
            );
        }

        DB::transaction(function () use ($ujian, $hasil, $nilaiAkhir, $batasRevisi, $catatan): void {
            $ujian->update([
                'status' => StatusUjian::Selesai,
                'hasil' => $hasil,
                'nilai' => $nilaiAkhir,
                'batas_revisi' => $hasil === HasilUjian::LulusRevisi ? $batasRevisi : null,
                'catatan' => $catatan,
            ]);

            $ujian->tugasAkhir->recordActivity(
                'exam_result',
                sprintf('%s: %s.', $ujian->jenis->label(), $hasil->label()),
            );
        });

        return $ujian->refresh();
    }

    /** Calls off a scheduled session. */
    public function batalkan(Ujian $ujian, string $alasan): Ujian
    {
        if ($ujian->status === StatusUjian::Selesai) {
            throw new AturanAkademikException(
                'Ujian yang sudah dicatat hasilnya tidak dapat dibatalkan.',
            );
        }

        if (blank($alasan)) {
            throw new AturanAkademikException('Pembatalan ujian wajib disertai alasan.');
        }

        $ujian->update(['status' => StatusUjian::Dibatalkan, 'catatan' => $alasan]);

        return $ujian->refresh();
    }

    /**
     * A panel must contain someone who did not supervise the work.
     *
     * Supervisors sitting on the panel is ordinary practice here and is not
     * blocked. A panel made up *only* of supervisors is different: it is the
     * work being examined by the people who produced it.
     *
     * Enforced for the closing defence only. Proposal and results seminars are
     * frequently run by the supervising team alone, and refusing that would
     * simply move those seminars out of the system.
     *
     * @param array<int, array{dosen_id: int, peran: PeranPenguji}> $panel
     */
    private function pastikanPanelSah(TugasAkhir $ta, JenisUjian $jenis, array $panel): void
    {
        $pengujiIds = array_map(static fn (array $k): int => (int) $k['dosen_id'], $panel);

        if (count($pengujiIds) !== count(array_unique($pengujiIds))) {
            throw new AturanAkademikException('Seorang dosen tidak dapat menempati dua kursi penguji.');
        }

        $ketua = array_filter($panel, static fn (array $k): bool => $k['peran'] === PeranPenguji::Ketua);

        if (count($ketua) !== 1) {
            throw new AturanAkademikException('Susunan penguji harus memiliki tepat satu ketua penguji.');
        }

        if (!$jenis->menutup()) {
            return;
        }

        $pembimbingIds = $ta->idPembimbing();
        $luar = array_diff($pengujiIds, $pembimbingIds);

        if ($luar === []) {
            throw new AturanAkademikException(
                'Susunan penguji seluruhnya terdiri atas pembimbing. Sidang akhir memerlukan sekurangnya '
                    .'satu penguji yang tidak membimbing karya ini — tanpa itu, karya diuji oleh pihak '
                    .'yang ikut menghasilkannya.',
            );
        }
    }

    /** Preconditions that apply to the closing defence only. */
    private function pastikanSyaratSidang(TugasAkhir $ta, JenisUjian $jenis): void
    {
        if (!$jenis->menutup()) {
            return;
        }

        if ($ta->pembimbingUtama() === null) {
            throw new AturanAkademikException(
                'Sidang akhir memerlukan pembimbing utama yang ditetapkan.',
            );
        }

        $minimum = (int) config('academic.tugas_akhir.min_bimbingan_sebelum_sidang');
        $tercatat = $ta->jumlahBimbinganDisetujui();

        if ($minimum > 0 && $tercatat < $minimum) {
            throw new AturanAkademikException(sprintf(
                'Sidang memerlukan sekurangnya %d bimbingan yang sudah disetujui pembimbing; '
                    .'baru %d yang tercatat. Log yang belum disetujui tidak dihitung.',
                $minimum,
                $tercatat,
            ));
        }

        if ($ta->ujian()->where('jenis', JenisUjian::Sidang->value)
            ->where('status', StatusUjian::Dijadwalkan->value)
            ->exists()
        ) {
            throw new AturanAkademikException(
                'Sudah ada sidang akhir yang dijadwalkan dan belum dicatat hasilnya.',
            );
        }
    }

    /**
     * Other examinations sharing a wall-clock window on the same date.
     *
     * Touching endpoints do not overlap — a session ending at 10:00 and one
     * starting at 10:00 are consecutive, the same rule the class timetable uses.
     *
     * whereDate() rather than a plain equality on the column. Engines disagree
     * about what sits in a date column: SQLite has no date type at all and
     * stores whatever string it is handed, so `where('tanggal', '2026-08-15')`
     * silently matches nothing against a stored '2026-08-15 00:00:00'.
     *
     * That mismatch matters more here than it would elsewhere, because this
     * check fails *open*: finding no rows reads as "no clash" and the defence is
     * scheduled into an occupied room. A guard that answers "all clear" when it
     * is broken is worse than no guard.
     *
     * @return Collection<int, Ujian>
     */
    private function ujianBeririsan(
        string $tanggal,
        string $jamMulai,
        string $jamSelesai,
        ?int $abaikanUjianId,
    ): Collection {
        return Ujian::query()
            ->with(['penguji', 'ruang', 'tugasAkhir.mahasiswa'])
            ->whereDate('tanggal', $tanggal)
            ->where('status', StatusUjian::Dijadwalkan->value)
            ->where('jam_mulai', '<', $jamSelesai)
            ->where('jam_selesai', '>', $jamMulai)
            ->when($abaikanUjianId !== null, fn ($q) => $q->whereKeyNot($abaikanUjianId))
            ->get();
    }

    /**
     * Weekly class slots that fall on this date.
     *
     * The reason this method exists: a defence booked into a room that has a
     * lecture in it every Tuesday is not a clash with any other defence, so
     * checking defences alone reports the slot as free.
     *
     * Restricted to classes whose term actually contains the date — a defence
     * held during the break collides with nothing.
     *
     * @return Collection<int, JadwalKuliah>
     */
    private function kelasBeririsan(string $tanggal, string $jamMulai, string $jamSelesai): Collection
    {
        // JadwalKuliah::HARI is 1 = Senin … 7 = Minggu, which is ISO-8601.
        $hari = Carbon::parse($tanggal)->dayOfWeekIso;

        return JadwalKuliah::query()
            ->with(['kelasKuliah.mataKuliah', 'kelasKuliah.dosen', 'ruang'])
            ->where('hari', $hari)
            ->where('jam_mulai', '<', $jamSelesai)
            ->where('jam_selesai', '>', $jamMulai)
            ->whereHas('kelasKuliah.tahunAkademik', fn ($q) => $q
                ->whereDate('tanggal_mulai', '<=', $tanggal)
                ->whereDate('tanggal_selesai', '>=', $tanggal))
            ->get();
    }

    /**
     * @param Collection<int, BentrokJadwal> $bentrok
     * @param Collection<int, Ujian> $ujianLain
     * @param Collection<int, JadwalKuliah> $kelasHariItu
     */
    private function periksaRuang(
        Collection $bentrok,
        Collection $ujianLain,
        Collection $kelasHariItu,
        ?int $ruangId,
    ): void {
        if ($ruangId === null) {
            return;
        }

        foreach ($ujianLain->where('ruang_id', $ruangId) as $lain) {
            $bentrok->push(BentrokJadwal::ruang(sprintf(
                'ruang %s sudah dipakai %s atas nama %s pada jam yang sama',
                $lain->ruang?->kode ?? '—',
                $lain->jenis->label(),
                $lain->tugasAkhir->mahasiswa->nama,
            )));
        }

        foreach ($kelasHariItu->where('ruang_id', $ruangId) as $kelas) {
            $bentrok->push(BentrokJadwal::ruang(sprintf(
                'ruang %s terpakai kuliah %s pada hari dan jam yang sama',
                $kelas->ruang?->kode ?? '—',
                $kelas->kelasKuliah->namaLengkap(),
            )));
        }
    }

    /**
     * @param Collection<int, BentrokJadwal> $bentrok
     * @param Collection<int, Ujian> $ujianLain
     * @param Collection<int, JadwalKuliah> $kelasHariItu
     * @param array<int, int> $pengujiIds
     */
    private function periksaPenguji(
        Collection $bentrok,
        Collection $ujianLain,
        Collection $kelasHariItu,
        array $pengujiIds,
    ): void {
        if ($pengujiIds === []) {
            return;
        }

        $nama = Dosen::whereIn('id', $pengujiIds)->get()->keyBy('id');

        foreach ($ujianLain as $lain) {
            foreach ($lain->penguji->pluck('dosen_id')->intersect($pengujiIds) as $dosenId) {
                $bentrok->push(BentrokJadwal::dosen(sprintf(
                    '%s sudah menguji %s atas nama %s pada jam yang sama',
                    $nama->get($dosenId)?->namaLengkap() ?? 'Dosen',
                    $lain->jenis->label(),
                    $lain->tugasAkhir->mahasiswa->nama,
                )));
            }
        }

        foreach ($kelasHariItu as $kelas) {
            foreach ($kelas->kelasKuliah->dosen->pluck('id')->intersect($pengujiIds) as $dosenId) {
                $bentrok->push(BentrokJadwal::dosen(sprintf(
                    '%s sedang mengajar %s pada hari dan jam yang sama',
                    $nama->get($dosenId)?->namaLengkap() ?? 'Dosen',
                    $kelas->kelasKuliah->namaLengkap(),
                )));
            }
        }
    }

    /**
     * @param Collection<int, BentrokJadwal> $bentrok
     * @param Collection<int, Ujian> $ujianLain
     */
    private function periksaMahasiswa(Collection $bentrok, Collection $ujianLain, TugasAkhir $ta): void
    {
        foreach ($ujianLain as $lain) {
            if ($lain->tugasAkhir->mahasiswa_id !== $ta->mahasiswa_id) {
                continue;
            }

            $bentrok->push(BentrokJadwal::mahasiswa(sprintf(
                'mahasiswa ini sudah dijadwalkan %s pada jam yang sama',
                $lain->jenis->label(),
            )));
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Akademik;

use App\DTOs\Akademik\BentrokJadwal;
use App\DTOs\Akademik\JadwalUjianData;
use App\DTOs\Akademik\PenyusunanUjianData;
use App\DTOs\Akademik\PesertaUjian;
use App\DTOs\Akademik\RencanaUjian;
use App\DTOs\Akademik\UsulanJadwalUjian;
use App\Enums\JenisUjianKelas;
use App\Enums\KrsStatus;
use App\Enums\StatusJadwalUjian;
use App\Exceptions\AturanAkademikException;
use App\Models\Akademik\JadwalUjian;
use App\Models\Akademik\JadwalUjianPengawas;
use App\Models\Akademik\JadwalUjianRuang;
use App\Models\Akademik\KelasKuliah;
use App\Models\Akademik\Ruang;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Keuangan\Tagihan;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\Staff;
use App\Notifications\Akademik\JadwalUjianBerubah;
use App\Notifications\Akademik\JadwalUjianTerbit;
use App\Services\Notifikasi\Notifier;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Mid-term and final examination sessions: arranging them, checking them, and
 * announcing them.
 *
 * The class timetable schedules offerings, so the worst it can do to a student
 * is offer two courses at once; the student then chooses. An exam timetable
 * schedules *people who are already enrolled*. A student holding two exams at
 * the same hour has no choice to make — one of them is missed. So the check
 * that the class timetable cannot make is the one that matters most here, and
 * it is made from approved study plans, not from the curriculum.
 *
 * Drafts may be saved with clashes; the registrar is mid-arrangement and needs
 * to see the whole picture, not be refused one row at a time. Publishing is the
 * gate. A session already published is live, so editing one is held to the same
 * gate immediately.
 */
class JadwalUjianService
{
    /** How many student numbers a clash message names before summarising. */
    private const CONTOH_NIM = 3;

    public function __construct(
        private readonly PresensiService $presensi,
        private readonly Notifier $notifier,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | One session
    |--------------------------------------------------------------------------
    */

    /**
     * Every clash the proposed session would have with the rest of the term.
     *
     * @return Collection<int, BentrokJadwal>
     */
    public function periksaBentrok(KelasKuliah $kelas, JadwalUjianData $data, ?JadwalUjian $abaikan = null): Collection
    {
        $kelas->loadMissing('mataKuliah');

        $usulan = $this->sesiDariData($kelas, $data, $abaikan?->id);

        $lain = $this->muatSesi(
            JadwalUjian::query()
                ->term($kelas->tahun_akademik_id)
                ->whereDate('tanggal', $data->tanggal)
                ->when($abaikan !== null, fn ($q) => $q->whereKeyNot($abaikan->id)),
        );

        $peserta = $this->pesertaPerKelas([$kelas->id, ...$lain->pluck('kelas_id')->all()]);

        return $this->bentrokSesi($usulan, $lain, $peserta);
    }

    /**
     * Saves a new draft session.
     *
     * Clashes do not stop a draft. They are returned so the screen can show
     * them; publishing refuses the blocking ones.
     *
     * @return array{jadwal: JadwalUjian, bentrok: Collection<int, BentrokJadwal>}
     */
    public function simpan(KelasKuliah $kelas, JadwalUjianData $data): array
    {
        $this->pastikanSah($kelas, $data);

        if ($kelas->jadwalUjian()->jenis($data->jenis)->exists()) {
            throw new AturanAkademikException(__('jadwal_ujian.galat.sudah_ada', [
                'jenis' => $data->jenis->singkatan(),
                'kelas' => $kelas->namaLengkap(),
            ]));
        }

        $bentrok = $this->periksaBentrok($kelas, $data);

        $jadwal = DB::transaction(function () use ($kelas, $data): JadwalUjian {
            $jadwal = JadwalUjian::create([
                'kelas_kuliah_id' => $kelas->id,
                'tahun_akademik_id' => $kelas->tahun_akademik_id,
                'jenis' => $data->jenis,
                'tanggal' => $data->tanggal,
                'jam_mulai' => $data->jamMulai,
                'jam_selesai' => $data->jamSelesai,
                'status' => StatusJadwalUjian::Draf,
                'catatan' => $data->catatan,
            ]);

            $this->tulisRuangDanPengawas($jadwal, $data);

            return $jadwal;
        });

        return ['jadwal' => $jadwal->refresh(), 'bentrok' => $bentrok];
    }

    /**
     * Changes a session's time, rooms or invigilators.
     *
     * A published session is live — students have planned around it — so a
     * change that would create a blocking clash is refused outright, and a
     * change that goes through is announced to everyone it touches, including
     * an invigilator who has just been taken off it.
     *
     * The exam type is not changed here. A UTS session turned into a UAS one is
     * a different session, and the card a student already printed says UTS.
     *
     * @return array{jadwal: JadwalUjian, bentrok: Collection<int, BentrokJadwal>}
     */
    public function perbarui(JadwalUjian $jadwal, JadwalUjianData $data): array
    {
        $kelas = $jadwal->kelasKuliah;

        if ($data->jenis !== $jadwal->jenis) {
            throw new AturanAkademikException(__('jadwal_ujian.galat.jenis_tetap'));
        }

        $this->pastikanSah($kelas, $data);

        $bentrok = $this->periksaBentrok($kelas, $data, $jadwal);

        if ($jadwal->sudahTerbit()) {
            $this->tolakBilaMenghalangi($bentrok, 'jadwal_ujian.galat.ubah_bentrok');
        }

        $jadwal->loadMissing(['ruang', 'pengawas']);
        $sebelum = $this->sidikJari($jadwal);
        $pengawasLama = $jadwal->pengawas->map->orang()->filter()->values();

        DB::transaction(function () use ($jadwal, $data): void {
            $jadwal->update([
                'tanggal' => $data->tanggal,
                'jam_mulai' => $data->jamMulai,
                'jam_selesai' => $data->jamSelesai,
                'catatan' => $data->catatan,
            ]);

            $jadwal->pengawas()->delete();
            $jadwal->ruang()->delete();

            $this->tulisRuangDanPengawas($jadwal, $data);
        });

        $jadwal = $jadwal->refresh()->load(['ruang.ruang', 'pengawas.dosen', 'pengawas.staff', 'kelasKuliah.mataKuliah']);

        if ($jadwal->sudahTerbit() && $sebelum !== $this->sidikJari($jadwal)) {
            $this->umumkanPerubahan($jadwal, $pengawasLama);
        }

        return ['jadwal' => $jadwal, 'bentrok' => $bentrok];
    }

    /**
     * Removes a draft.
     *
     * A published session is not deleted: students have it on their cards.
     * It is moved, and the move is announced.
     */
    public function hapus(JadwalUjian $jadwal): void
    {
        if ($jadwal->sudahTerbit()) {
            throw new AturanAkademikException(__('jadwal_ujian.galat.hapus_terbit'));
        }

        $jadwal->delete();
    }

    /*
    |--------------------------------------------------------------------------
    | A whole term
    |--------------------------------------------------------------------------
    */

    /**
     * Every clash of every session in scope, against every other session of the
     * term — drafts included, because a draft is next week's published session.
     *
     * @return array<int, Collection<int, BentrokJadwal>> keyed by session id; sessions without clashes are absent
     */
    public function laporanBentrok(TahunAkademik $term, ?JenisUjianKelas $jenis = null, ?int $prodiId = null): array
    {
        $semua = $this->muatSesi(JadwalUjian::query()->term($term->id));
        $peserta = $this->pesertaPerKelas($semua->pluck('kelas_id')->unique()->all());

        $laporan = [];

        foreach ($semua as $sesi) {
            if (!$this->dalamCakupan($sesi, $jenis, $prodiId)) {
                continue;
            }

            $bentrok = $this->bentrokSesi($sesi, $semua, $peserta);

            if ($bentrok->isNotEmpty()) {
                $laporan[$sesi['id']] = $bentrok;
            }
        }

        return $laporan;
    }

    /**
     * Publishes every draft in scope, or none of them.
     *
     * All-or-nothing on purpose. Publishing the clean half of a faculty's exams
     * hands students a timetable with holes in it, and the half that clashed is
     * then fixed against a published set it can no longer move.
     *
     * Drafts are checked against what is already published and against each
     * other — not against drafts outside the scope, which are checked in turn
     * when they are published.
     *
     * @return int the number of sessions published
     */
    public function terbitkan(TahunAkademik $term, JenisUjianKelas $jenis, ?int $prodiId = null): int
    {
        $semua = $this->muatSesi(JadwalUjian::query()->term($term->id));

        $draf = $semua->filter(fn (array $s): bool => !$s['terbit']
            && $this->dalamCakupan($s, $jenis, $prodiId))->values();

        if ($draf->isEmpty()) {
            throw new AturanAkademikException(__('jadwal_ujian.galat.tidak_ada_draf'));
        }

        $pembanding = $semua->filter(fn (array $s): bool => $s['terbit'])->concat($draf)->values();
        $peserta = $this->pesertaPerKelas($semua->pluck('kelas_id')->unique()->all());

        $penghalang = $draf
            ->flatMap(fn (array $s): Collection => $this->bentrokSesi($s, $pembanding, $peserta)
                ->filter(fn (BentrokJadwal $b): bool => $b->menghalangi)
                ->map(fn (BentrokJadwal $b): string => $s['label'].': '.$b->pesan))
            ->values();

        if ($penghalang->isNotEmpty()) {
            throw new AturanAkademikException(__('jadwal_ujian.galat.terbit_bentrok', [
                'jumlah' => $penghalang->count(),
                'contoh' => $penghalang->take(3)->implode('; '),
            ]));
        }

        $ids = $draf->pluck('id')->all();

        DB::transaction(function () use ($ids): void {
            JadwalUjian::whereIn('id', $ids)->get()->each(function (JadwalUjian $j): void {
                $j->update(['status' => StatusJadwalUjian::Terbit, 'diterbitkan_at' => now()]);
                $j->recordActivity('exam_schedule_published', __('jadwal_ujian.log.terbit', [
                    'jenis' => $j->jenis->singkatan(),
                    'waktu' => $j->rentangWaktu(),
                ]));
            });
        });

        $this->umumkanTerbit($term, $jenis, $ids, $peserta);

        return count($ids);
    }

    /**
     * The registrar's list: every session in scope, drafts first, then by date.
     *
     * @return Collection<int, JadwalUjian>
     */
    public function daftar(TahunAkademik $term, ?JenisUjianKelas $jenis = null, ?int $prodiId = null): Collection
    {
        return JadwalUjian::query()
            ->with(['kelasKuliah.mataKuliah', 'kelasKuliah.prodi', 'ruang.ruang', 'pengawas.dosen', 'pengawas.staff'])
            ->term($term->id)
            ->when($jenis !== null, fn ($q) => $q->jenis($jenis))
            ->when($prodiId !== null, fn ($q) => $q->whereHas('kelasKuliah', fn ($k) => $k->where('prodi_id', $prodiId)))
            ->orderBy('status')
            ->orderBy('tanggal')
            ->orderBy('jam_mulai')
            ->get();
    }

    /** Classes in scope that have no session of this type yet. */
    public function jumlahKelasTanpaJadwal(TahunAkademik $term, JenisUjianKelas $jenis, ?int $prodiId = null): int
    {
        return KelasKuliah::query()
            ->where('tahun_akademik_id', $term->id)
            ->when($prodiId !== null, fn ($q) => $q->where('prodi_id', $prodiId))
            ->whereDoesntHave('jadwalUjian', fn ($q) => $q->where('jenis', $jenis->value))
            ->count();
    }

    /*
    |--------------------------------------------------------------------------
    | Bulk scheduling
    |--------------------------------------------------------------------------
    */

    /**
     * Lays out every class still without a session of this type.
     *
     * Greedy, largest class first: the classes that need the most seats get the
     * pick of the rooms, and a small class fits in whatever is left. Each class
     * takes the earliest sitting where none of its students is already sitting
     * another exam and enough rooms are free to seat all of them — in one room
     * when one is large enough, split across the largest free rooms when not.
     *
     * Existing sessions of the term, drafts included, occupy their rooms,
     * invigilators and students. Nothing is saved.
     */
    public function susun(PenyusunanUjianData $data): RencanaUjian
    {
        $term = TahunAkademik::findOrFail($data->tahunAkademikId);

        $kelas = KelasKuliah::query()
            ->with(['mataKuliah', 'dosen'])
            ->where('tahun_akademik_id', $term->id)
            ->when($data->prodiId !== null, fn ($q) => $q->where('prodi_id', $data->prodiId))
            ->whereDoesntHave('jadwalUjian', fn ($q) => $q->where('jenis', $data->jenis->value))
            ->get();

        $terpakai = $this->muatSesi(JadwalUjian::query()->term($term->id));
        $peserta = $this->pesertaPerKelas([...$kelas->pluck('id')->all(), ...$terpakai->pluck('kelas_id')->all()]);

        $ruang = Ruang::query()
            ->where('is_active', true)
            ->where('kapasitas', '>', 0)
            ->when($data->ruangIds !== [], fn ($q) => $q->whereIn('id', $data->ruangIds))
            ->orderBy('kapasitas')
            ->orderBy('kode')
            ->get();

        $slot = $data->slot();
        $usulan = collect();
        $tidakTerjadwal = collect();

        $urutan = $kelas->sortBy([
            fn (KelasKuliah $a, KelasKuliah $b): int => count($peserta[$b->id] ?? []) <=> count($peserta[$a->id] ?? []),
            fn (KelasKuliah $a, KelasKuliah $b): int => strcmp((string) $a->mataKuliah?->kode, (string) $b->mataKuliah?->kode),
        ]);

        foreach ($urutan as $k) {
            $mahasiswa = array_keys($peserta[$k->id] ?? []);

            if ($mahasiswa === []) {
                $tidakTerjadwal->push(['kelas' => $k, 'alasan' => __('jadwal_ujian.susun.tanpa_peserta')]);

                continue;
            }

            $tempat = $this->cariSlot($k, $mahasiswa, $slot, $ruang, $terpakai, $peserta, $data);

            if ($tempat === null) {
                $tidakTerjadwal->push(['kelas' => $k, 'alasan' => $slot === []
                    ? __('jadwal_ujian.susun.tanpa_slot')
                    : __('jadwal_ujian.susun.tidak_muat', ['jumlah' => count($mahasiswa)])]);

                continue;
            }

            $item = new UsulanJadwalUjian(
                kelas: $k,
                jenis: $data->jenis,
                tanggal: $tempat['slot']['tanggal'],
                jamMulai: $tempat['slot']['mulai'],
                jamSelesai: $tempat['slot']['selesai'],
                jumlahPeserta: count($mahasiswa),
                ruang: $tempat['ruang'],
                pengawas: $tempat['pengawas'],
            );

            $usulan->push($item);

            // The proposal now occupies its sitting for every class after it.
            $terpakai->push($this->sesiDariData($k, $item->toData()));
        }

        return new RencanaUjian(
            usulan: $usulan->sortBy(fn (UsulanJadwalUjian $u): string => $u->tanggal.$u->jamMulai.$u->kelas->mataKuliah?->kode)->values(),
            tidakTerjadwal: $tidakTerjadwal->values(),
        );
    }

    /**
     * Recomputes the plan and saves it as drafts.
     *
     * Recomputed rather than posted back from the preview: what is saved is
     * what the rules produce from the database as it stands now, not a form a
     * second registrar may have made stale in the meantime.
     *
     * @return Collection<int, JadwalUjian>
     */
    public function simpanRencana(PenyusunanUjianData $data): Collection
    {
        $rencana = $this->susun($data);

        return DB::transaction(fn (): Collection => $rencana->usulan->map(function (UsulanJadwalUjian $u): JadwalUjian {
            $isi = $u->toData();

            $jadwal = JadwalUjian::create([
                'kelas_kuliah_id' => $u->kelas->id,
                'tahun_akademik_id' => $u->kelas->tahun_akademik_id,
                'jenis' => $isi->jenis,
                'tanggal' => $isi->tanggal,
                'jam_mulai' => $isi->jamMulai,
                'jam_selesai' => $isi->jamSelesai,
                'status' => StatusJadwalUjian::Draf,
            ]);

            $this->tulisRuangDanPengawas($jadwal, $isi);

            return $jadwal;
        }));
    }

    /*
    |--------------------------------------------------------------------------
    | Who sits, where, and whether they may
    |--------------------------------------------------------------------------
    */

    /**
     * The session's list: every student enrolled through an approved study
     * plan, seated in NIM order, room by room.
     *
     * Seating by NIM is what invigilators already expect, and it is the only
     * order that survives a reprint: a student who adds the course late slots
     * into place instead of displacing everyone after them by arrival time.
     *
     * Eligibility follows the exam-card policy in config/dokumen.php — one
     * policy, read by both the card and the list.
     *
     * @return Collection<int, PesertaUjian>
     */
    public function peserta(JadwalUjian $jadwal): Collection
    {
        $jadwal->loadMissing(['ruang.ruang', 'kelasKuliah']);

        $mahasiswa = Mahasiswa::query()
            ->whereIn('id', array_keys($this->pesertaPerKelas([$jadwal->kelas_kuliah_id])[$jadwal->kelas_kuliah_id] ?? []))
            ->orderBy('nim')
            ->get();

        $tempat = $this->tempatDuduk($jadwal, $mahasiswa->pluck('id')->all());

        $periksaKehadiran = $jadwal->jenis->butuhKehadiran();
        $kehadiran = $periksaKehadiran ? $this->presensi->rekapKelas($jadwal->kelasKuliah) : [];
        $tahanKehadiran = $periksaKehadiran && (bool) config('dokumen.kartu_ujian.tahan_bila_kehadiran_kurang');

        $tahanTunggakan = (bool) config('dokumen.kartu_ujian.tahan_bila_menunggak');
        $tunggakan = $tahanTunggakan
            ? Tagihan::tunggakanPerMahasiswa($mahasiswa->pluck('id')->all())
            : collect();

        return $mahasiswa->map(function (Mahasiswa $m) use ($tempat, $periksaKehadiran, $kehadiran, $tahanKehadiran, $tahanTunggakan, $tunggakan): PesertaUjian {
            $persen = $periksaKehadiran ? ($kehadiran[$m->id] ?? null) : null;
            $memenuhi = !$periksaKehadiran || $this->presensi->memenuhiAmbangUas($persen);
            $hutang = (int) ($tunggakan[$m->id] ?? 0);

            $alasan = [];

            if ($tahanKehadiran && !$memenuhi) {
                $alasan[] = __('jadwal_ujian.kelayakan.kehadiran', [
                    'minimum' => (int) config('academic.attendance.min_percent_for_final_exam'),
                ]);
            }

            if ($tahanTunggakan && $hutang > 0) {
                $alasan[] = __('jadwal_ujian.kelayakan.tunggakan');
            }

            return new PesertaUjian(
                mahasiswa: $m,
                ruang: $tempat[$m->id] ?? null,
                persenKehadiran: $persen,
                memenuhiKehadiran: $memenuhi,
                tunggakan: $hutang,
                layak: $alasan === [],
                alasan: $alasan,
            );
        });
    }

    /** The room one student sits the session in, or null when they have no seat. */
    public function ruangUntuk(JadwalUjian $jadwal, Mahasiswa $mahasiswa): ?Ruang
    {
        return $this->ruangUntukBanyak(collect([$jadwal]), $mahasiswa)[$jadwal->id] ?? null;
    }

    /**
     * The student's room in each of several sessions, in one enrolment query.
     *
     * A student's exam list or card shows eight sessions; placing them one by
     * one is two queries each.
     *
     * @param Collection<int, JadwalUjian> $jadwal with ruang.ruang loaded
     * @return array<int, Ruang|null> keyed by session id
     */
    public function ruangUntukBanyak(Collection $jadwal, Mahasiswa $mahasiswa): array
    {
        $peserta = $this->pesertaPerKelas($jadwal->pluck('kelas_kuliah_id')->all());

        return $jadwal->mapWithKeys(fn (JadwalUjian $j): array => [
            $j->id => $this->tempatDuduk($j, array_keys($peserta[$j->kelas_kuliah_id] ?? []))[$mahasiswa->id] ?? null,
        ])->all();
    }

    /**
     * Published sessions of a student's approved study plan.
     *
     * @return Collection<int, JadwalUjian>
     */
    public function untukMahasiswa(Mahasiswa $mahasiswa, TahunAkademik $term, ?JenisUjianKelas $jenis = null): Collection
    {
        return JadwalUjian::query()
            ->with(['kelasKuliah.mataKuliah', 'ruang.ruang'])
            ->term($term->id)
            ->terbit()
            ->when($jenis !== null, fn ($q) => $q->jenis($jenis))
            ->whereHas('kelasKuliah.krsDetail.krs', fn ($q) => $q
                ->where('mahasiswa_id', $mahasiswa->id)
                ->where('tahun_akademik_id', $term->id)
                ->where('status', KrsStatus::Disetujui->value))
            ->orderBy('tanggal')
            ->orderBy('jam_mulai')
            ->get();
    }

    /**
     * Published sessions a lecturer teaches or invigilates.
     *
     * @return Collection<int, JadwalUjian>
     */
    public function untukDosen(Dosen $dosen, TahunAkademik $term): Collection
    {
        return JadwalUjian::query()
            ->with(['kelasKuliah.mataKuliah', 'kelasKuliah.dosen', 'ruang.ruang', 'pengawas.dosen', 'pengawas.staff', 'pengawas.jadwalUjianRuang.ruang'])
            ->term($term->id)
            ->terbit()
            ->where(fn ($q) => $q
                ->whereHas('kelasKuliah.dosen', fn ($d) => $d->where('dosen.id', $dosen->id))
                ->orWhereHas('pengawas', fn ($p) => $p->where('dosen_id', $dosen->id)))
            ->orderBy('tanggal')
            ->orderBy('jam_mulai')
            ->get();
    }

    /**
     * The exam a card printed today is for: the later type that has anything
     * published, so a card printed in finals week is a UAS card even though the
     * UTS sessions are still on record.
     */
    public function jenisTerbit(TahunAkademik $term): ?JenisUjianKelas
    {
        $terbit = JadwalUjian::query()->term($term->id)->terbit()->distinct()->pluck('jenis')
            ->map(fn ($j): JenisUjianKelas => $j instanceof JenisUjianKelas ? $j : JenisUjianKelas::from($j));

        foreach (array_reverse(JenisUjianKelas::cases()) as $jenis) {
            if ($terbit->contains($jenis)) {
                return $jenis;
            }
        }

        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    private function pastikanSah(KelasKuliah $kelas, JadwalUjianData $data): void
    {
        if ($data->jamSelesai <= $data->jamMulai) {
            throw new AturanAkademikException(__('jadwal_ujian.galat.jam'));
        }

        $term = $kelas->tahunAkademik;
        $tanggal = Carbon::parse($data->tanggal);

        if ($term !== null && ($tanggal->lt($term->tanggal_mulai) || $tanggal->gt($term->tanggal_selesai))) {
            throw new AturanAkademikException(__('jadwal_ujian.galat.di_luar_term', ['term' => $term->nama]));
        }

        $ruangIds = array_column($data->ruang, 'ruang_id');

        if (count($ruangIds) !== count(array_unique($ruangIds))) {
            throw new AturanAkademikException(__('jadwal_ujian.galat.ruang_ganda'));
        }

        $orang = array_map(
            static fn (array $p): string => $p['dosen_id'] !== null ? 'dosen:'.$p['dosen_id'] : 'staff:'.$p['staff_id'],
            $data->pengawas,
        );

        if (count($orang) !== count(array_unique($orang))) {
            throw new AturanAkademikException(__('jadwal_ujian.galat.pengawas_ganda'));
        }

        foreach ($data->pengawas as $p) {
            if ($p['ruang_id'] !== null && !in_array($p['ruang_id'], $ruangIds, true)) {
                throw new AturanAkademikException(__('jadwal_ujian.galat.ruang_pengawas'));
            }
        }
    }

    /** @param Collection<int, BentrokJadwal> $bentrok */
    private function tolakBilaMenghalangi(Collection $bentrok, string $kunci): void
    {
        $penghalang = $bentrok->filter(fn (BentrokJadwal $b): bool => $b->menghalangi);

        if ($penghalang->isNotEmpty()) {
            throw new AturanAkademikException(__($kunci, [
                'daftar' => $penghalang->pluck('pesan')->implode('; '),
            ]));
        }
    }

    private function tulisRuangDanPengawas(JadwalUjian $jadwal, JadwalUjianData $data): void
    {
        $kapasitas = Ruang::whereIn('id', array_column($data->ruang, 'ruang_id'))->pluck('kapasitas', 'id');
        $barisRuang = [];

        foreach (array_values($data->ruang) as $i => $r) {
            $barisRuang[$r['ruang_id']] = JadwalUjianRuang::create([
                'jadwal_ujian_id' => $jadwal->id,
                'ruang_id' => $r['ruang_id'],
                'kursi' => $r['kursi'] ?? (int) ($kapasitas[$r['ruang_id']] ?? 0),
                'urutan' => $i + 1,
            ]);
        }

        foreach ($data->pengawas as $p) {
            JadwalUjianPengawas::create([
                'jadwal_ujian_id' => $jadwal->id,
                'dosen_id' => $p['dosen_id'],
                'staff_id' => $p['dosen_id'] === null ? $p['staff_id'] : null,
                'jadwal_ujian_ruang_id' => $p['ruang_id'] !== null ? $barisRuang[$p['ruang_id']]->id : null,
            ]);
        }
    }

    /**
     * Seats students room by room in the order given.
     *
     * @param list<int> $mahasiswaIds already in seating order
     * @return array<int, Ruang> student id => room; students past the last seat are absent
     */
    private function tempatDuduk(JadwalUjian $jadwal, array $mahasiswaIds): array
    {
        $jadwal->loadMissing('ruang.ruang');

        $tempat = [];
        $i = 0;

        foreach ($jadwal->ruang as $baris) {
            for ($kursi = 0; $kursi < $baris->kursi && $i < count($mahasiswaIds); $kursi++, $i++) {
                $tempat[$mahasiswaIds[$i]] = $baris->ruang;
            }
        }

        return $tempat;
    }

    /**
     * Students enrolled in each class through an approved, live study plan.
     *
     * One query for any number of classes. Clash checks across a term compare
     * every pair of sessions on a day; asking per class would be one query per
     * session per comparison.
     *
     * @param array<int, int> $kelasIds
     * @return array<int, array<int, string>> kelas id => [mahasiswa id => NIM], NIM-ordered
     */
    private function pesertaPerKelas(array $kelasIds): array
    {
        $kelasIds = array_values(array_unique(array_filter($kelasIds)));

        if ($kelasIds === []) {
            return [];
        }

        $hasil = [];

        DB::table('krs_detail')
            ->join('krs', 'krs.id', '=', 'krs_detail.krs_id')
            ->join('mahasiswa', 'mahasiswa.id', '=', 'krs.mahasiswa_id')
            ->whereIn('krs_detail.kelas_kuliah_id', $kelasIds)
            ->where('krs.status', KrsStatus::Disetujui->value)
            ->whereNull('krs_detail.deleted_at')
            ->whereNull('krs.deleted_at')
            ->orderBy('mahasiswa.nim')
            ->select('krs_detail.kelas_kuliah_id', 'krs.mahasiswa_id', 'mahasiswa.nim')
            ->get()
            ->each(function ($baris) use (&$hasil): void {
                $hasil[(int) $baris->kelas_kuliah_id][(int) $baris->mahasiswa_id] = (string) $baris->nim;
            });

        return $hasil;
    }

    /**
     * Sessions as plain arrays, the one shape the clash check reads.
     *
     * Saved sessions, the one being edited, and the bulk scheduler's proposals
     * all go through the same check; turning each into the same shape first is
     * what makes that possible without three versions of it.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function muatSesi($query): Collection
    {
        return $query
            ->with(['kelasKuliah.mataKuliah', 'ruang.ruang', 'pengawas.dosen', 'pengawas.staff'])
            ->get()
            ->map(fn (JadwalUjian $j): array => $this->sesiDariModel($j));
    }

    /** @return array<string, mixed> */
    private function sesiDariModel(JadwalUjian $j): array
    {
        return [
            'id' => $j->id,
            'kelas_id' => $j->kelas_kuliah_id,
            'prodi_id' => $j->kelasKuliah?->prodi_id,
            'jenis' => $j->jenis,
            'label' => $j->jenis->singkatan().' '.$j->kelasKuliah?->namaLengkap(),
            'tanggal' => $j->tanggal->toDateString(),
            'mulai' => JadwalUjian::jam($j->jam_mulai),
            'selesai' => JadwalUjian::jam($j->jam_selesai),
            'terbit' => $j->sudahTerbit(),
            'ruang' => $j->ruang->mapWithKeys(fn (JadwalUjianRuang $r): array => [$r->ruang_id => [
                'kode' => $r->ruang?->kode ?? '—',
                'kursi' => (int) $r->kursi,
                'kapasitas' => (int) ($r->ruang?->kapasitas ?? 0),
            ]])->all(),
            'pengawas' => $j->pengawas->mapWithKeys(fn (JadwalUjianPengawas $p): array => [$p->kunci() => $p->namaOrang()])->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function sesiDariData(KelasKuliah $kelas, JadwalUjianData $data, ?int $id = null): array
    {
        $ruang = Ruang::whereIn('id', array_column($data->ruang, 'ruang_id'))->get()->keyBy('id');

        $dosen = Dosen::whereIn('id', array_filter(array_column($data->pengawas, 'dosen_id')))->get()->keyBy('id');
        $staf = Staff::whereIn('id', array_filter(array_column($data->pengawas, 'staff_id')))->get()->keyBy('id');

        $pengawas = [];

        foreach ($data->pengawas as $p) {
            if ($p['dosen_id'] !== null) {
                $pengawas['dosen:'.$p['dosen_id']] = $dosen->get($p['dosen_id'])?->namaLengkap() ?? '—';
            } else {
                $pengawas['staff:'.$p['staff_id']] = $staf->get($p['staff_id'])?->namaLengkap() ?? '—';
            }
        }

        return [
            'id' => $id,
            'kelas_id' => $kelas->id,
            'prodi_id' => $kelas->prodi_id,
            'jenis' => $data->jenis,
            'label' => $data->jenis->singkatan().' '.$kelas->namaLengkap(),
            'tanggal' => $data->tanggal,
            'mulai' => $data->jamMulai,
            'selesai' => $data->jamSelesai,
            'terbit' => false,
            'ruang' => collect($data->ruang)->mapWithKeys(fn (array $r): array => [$r['ruang_id'] => [
                'kode' => $ruang->get($r['ruang_id'])?->kode ?? '—',
                'kursi' => $r['kursi'] ?? (int) ($ruang->get($r['ruang_id'])?->kapasitas ?? 0),
                'kapasitas' => (int) ($ruang->get($r['ruang_id'])?->kapasitas ?? 0),
            ]])->all(),
            'pengawas' => $pengawas,
        ];
    }

    /** @param array<string, mixed> $sesi */
    private function dalamCakupan(array $sesi, ?JenisUjianKelas $jenis, ?int $prodiId): bool
    {
        return ($jenis === null || $sesi['jenis'] === $jenis)
            && ($prodiId === null || $sesi['prodi_id'] === $prodiId);
    }

    /**
     * @param array<string, mixed> $a
     * @param array<string, mixed> $b
     */
    private function beririsan(array $a, array $b): bool
    {
        // Touching endpoints are consecutive, not simultaneous — the same rule
        // as the class timetable.
        return $a['tanggal'] === $b['tanggal']
            && $a['mulai'] < $b['selesai']
            && $b['mulai'] < $a['selesai'];
    }

    /**
     * Every clash of one session against a set of others, plus its own seats.
     *
     * @param array<string, mixed> $sesi
     * @param Collection<int, array<string, mixed>> $lain
     * @param array<int, array<int, string>> $peserta
     * @return Collection<int, BentrokJadwal>
     */
    private function bentrokSesi(array $sesi, Collection $lain, array $peserta): Collection
    {
        $bentrok = collect();
        $milikSendiri = $peserta[$sesi['kelas_id']] ?? [];

        foreach ($lain as $l) {
            if (($sesi['id'] !== null && $l['id'] === $sesi['id']) || !$this->beririsan($sesi, $l)) {
                continue;
            }

            foreach (array_intersect_key($sesi['ruang'], $l['ruang']) as $r) {
                $bentrok->push(BentrokJadwal::ruang(__('jadwal_ujian.bentrok.ruang', [
                    'ruang' => $r['kode'],
                    'sesi' => $l['label'],
                ])));
            }

            foreach (array_intersect_key($sesi['pengawas'], $l['pengawas']) as $nama) {
                $bentrok->push(BentrokJadwal::pengawas(__('jadwal_ujian.bentrok.pengawas', [
                    'nama' => $nama,
                    'sesi' => $l['label'],
                ])));
            }

            // A class against its own other session (UTS row while editing UAS
            // on the same morning, say) still shares every student — that is
            // a real clash, so the class id is not excluded here.
            $sama = array_intersect_key($milikSendiri, $peserta[$l['kelas_id']] ?? []);

            if ($sama !== []) {
                $bentrok->push(BentrokJadwal::mahasiswa(__('jadwal_ujian.bentrok.mahasiswa', [
                    'jumlah' => count($sama),
                    'sesi' => $l['label'],
                    'nim' => implode(', ', array_slice(array_values($sama), 0, self::CONTOH_NIM))
                        .(count($sama) > self::CONTOH_NIM ? ', …' : ''),
                ])));
            }
        }

        $kursi = array_sum(array_column($sesi['ruang'], 'kursi'));

        if ($sesi['ruang'] === []) {
            $bentrok->push(BentrokJadwal::kursi(__('jadwal_ujian.bentrok.tanpa_ruang')));
        } elseif (count($milikSendiri) > $kursi) {
            $bentrok->push(BentrokJadwal::kursi(__('jadwal_ujian.bentrok.kursi', [
                'peserta' => count($milikSendiri),
                'kursi' => $kursi,
            ])));
        }

        foreach ($sesi['ruang'] as $r) {
            if ($r['kapasitas'] > 0 && $r['kursi'] > $r['kapasitas']) {
                $bentrok->push(BentrokJadwal::kapasitas(__('jadwal_ujian.bentrok.kapasitas', [
                    'ruang' => $r['kode'],
                    'kursi' => $r['kursi'],
                    'kapasitas' => $r['kapasitas'],
                ])));
            }
        }

        return $bentrok;
    }

    /**
     * The earliest sitting that seats every student of the class without
     * putting any of them in two exams at once.
     *
     * @param list<int> $mahasiswa
     * @param list<array{tanggal: string, mulai: string, selesai: string}> $slot
     * @param Collection<int, Ruang> $ruang ordered by capacity, smallest first
     * @param Collection<int, array<string, mixed>> $terpakai
     * @param array<int, array<int, string>> $peserta
     * @return array{slot: array{tanggal: string, mulai: string, selesai: string}, ruang: list<array{ruang: Ruang, kursi: int}>, pengawas: list<Dosen>}|null
     */
    private function cariSlot(
        KelasKuliah $kelas,
        array $mahasiswa,
        array $slot,
        Collection $ruang,
        Collection $terpakai,
        array $peserta,
        PenyusunanUjianData $data,
    ): ?array {
        $milikSendiri = array_flip($mahasiswa);

        foreach ($slot as $s) {
            $calon = ['tanggal' => $s['tanggal'], 'mulai' => $s['mulai'], 'selesai' => $s['selesai']];
            $bersamaan = $terpakai->filter(fn (array $t): bool => $this->beririsan($calon, $t));

            $mahasiswaSibuk = $bersamaan->contains(
                fn (array $t): bool => array_intersect_key($milikSendiri, $peserta[$t['kelas_id']] ?? []) !== [],
            );

            if ($mahasiswaSibuk) {
                continue;
            }

            $ruangSibuk = $bersamaan->flatMap(fn (array $t): array => array_keys($t['ruang']))->unique()->all();
            $bebas = $ruang->reject(fn (Ruang $r): bool => in_array($r->id, $ruangSibuk, true))->values();

            $pilihan = $this->pilihRuang($bebas, count($mahasiswa));

            if ($pilihan === null) {
                continue;
            }

            $pengawas = [];

            if ($data->pengampuMengawas) {
                $orangSibuk = $bersamaan->flatMap(fn (array $t): array => array_keys($t['pengawas']))->unique()->all();
                $pengawas = $kelas->dosen
                    ->reject(fn (Dosen $d): bool => in_array('dosen:'.$d->id, $orangSibuk, true))
                    ->values()
                    ->all();
            }

            return ['slot' => $s, 'ruang' => $pilihan, 'pengawas' => $pengawas];
        }

        return null;
    }

    /**
     * The smallest single room that seats everyone; failing that, the fewest
     * large rooms that do together.
     *
     * @param Collection<int, Ruang> $bebas smallest first
     * @return list<array{ruang: Ruang, kursi: int}>|null
     */
    private function pilihRuang(Collection $bebas, int $jumlah): ?array
    {
        $satu = $bebas->first(fn (Ruang $r): bool => (int) $r->kapasitas >= $jumlah);

        if ($satu !== null) {
            return [['ruang' => $satu, 'kursi' => (int) $satu->kapasitas]];
        }

        $pilihan = [];
        $kursi = 0;

        foreach ($bebas->sortByDesc('kapasitas') as $r) {
            $pilihan[] = ['ruang' => $r, 'kursi' => (int) $r->kapasitas];
            $kursi += (int) $r->kapasitas;

            if ($kursi >= $jumlah) {
                return $pilihan;
            }
        }

        return null;
    }

    /** What a student or invigilator would notice changing. */
    private function sidikJari(JadwalUjian $jadwal): string
    {
        return implode('|', [
            $jadwal->tanggal->toDateString(),
            JadwalUjian::jam($jadwal->jam_mulai),
            JadwalUjian::jam($jadwal->jam_selesai),
            $jadwal->ruang->map(fn (JadwalUjianRuang $r): string => $r->ruang_id.':'.$r->kursi)->implode(','),
            $jadwal->pengawas->map->kunci()->sort()->implode(','),
        ]);
    }

    /**
     * One message per person, however many of their sessions were published.
     *
     * A student with eight exams told eight times about one announcement stops
     * reading the ninth message, and the ninth is the one that says a room moved.
     *
     * @param list<int> $ids
     * @param array<int, array<int, string>> $peserta
     */
    private function umumkanTerbit(TahunAkademik $term, JenisUjianKelas $jenis, array $ids, array $peserta): void
    {
        $sesi = JadwalUjian::with(['kelasKuliah.dosen', 'pengawas.dosen', 'pengawas.staff'])->whereIn('id', $ids)->get();

        $hitung = [];
        $orang = [];

        $catat = function (object $penerima) use (&$hitung, &$orang): void {
            $kunci = $penerima::class.':'.$penerima->getKey();
            $orang[$kunci] = $penerima;
            $hitung[$kunci] = ($hitung[$kunci] ?? 0) + 1;
        };

        $mahasiswa = Mahasiswa::whereIn('id', $sesi->flatMap(fn (JadwalUjian $j): array => array_keys($peserta[$j->kelas_kuliah_id] ?? []))->unique()->all())
            ->get()
            ->keyBy('id');

        foreach ($sesi as $j) {
            foreach (array_keys($peserta[$j->kelas_kuliah_id] ?? []) as $id) {
                if ($mahasiswa->has($id)) {
                    $catat($mahasiswa[$id]);
                }
            }

            $staf = $j->kelasKuliah->dosen->concat($j->pengawas->map->orang()->filter())->unique(fn ($o): string => $o::class.':'.$o->getKey());

            foreach ($staf as $o) {
                $catat($o);
            }
        }

        foreach ($orang as $kunci => $penerima) {
            $this->notifier->kirim($penerima, new JadwalUjianTerbit($term, $jenis, $hitung[$kunci]));
        }
    }

    /** @param Collection<int, Dosen|Staff> $pengawasLama */
    private function umumkanPerubahan(JadwalUjian $jadwal, Collection $pengawasLama): void
    {
        $peserta = $this->pesertaPerKelas([$jadwal->kelas_kuliah_id])[$jadwal->kelas_kuliah_id] ?? [];

        $penerima = Mahasiswa::whereIn('id', array_keys($peserta))->get()
            ->concat($jadwal->kelasKuliah->dosen)
            ->concat($jadwal->pengawas->map->orang()->filter())
            ->concat($pengawasLama)
            ->unique(fn ($o): string => $o::class.':'.$o->getKey())
            ->values();

        $jadwal->recordActivity('exam_schedule_changed', __('jadwal_ujian.log.ubah', ['waktu' => $jadwal->rentangWaktu()]));

        $this->notifier->kirimBanyak($penerima, new JadwalUjianBerubah($jadwal));
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Akademik;

use App\DTOs\Akademik\FilterKalender;
use App\DTOs\Akademik\PeristiwaKalender;
use App\DTOs\Akademik\PeristiwaKalenderData;
use App\Enums\JenisPeristiwa;
use App\Enums\JenisUjianKelas;
use App\Enums\SasaranPeristiwa;
use App\Enums\StatusPeristiwa;
use App\Enums\UserRole;
use App\Exceptions\AturanAkademikException;
use App\Models\Akademik\JadwalUjian;
use App\Models\Akademik\KalenderPeristiwa;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Edom\EdomPeriode;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Kemahasiswaan\WisudaPeriode;
use App\Models\Kemahasiswaan\Yudisium;
use App\Models\Pmb\PmbGelombang;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\Staff;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The academic calendar: one read layer over dates other modules own, plus the
 * few entries nothing else owns.
 *
 * Nothing derived is copied. The KRS window shown here is the very column
 * TahunAkademik::krsDibuka() reads, the UTS period is the published exam
 * timetable, and graduation day is the graduation period — change any of them
 * on its own screen and the calendar shows the change on the next read. A
 * calendar that kept its own copy would be right on the day it was filled in
 * and wrong on the day somebody moved the exams.
 *
 * Who sees what is decided here, once, for the three portals and the iCalendar
 * feed alike: by audience (SasaranPeristiwa::terlihatOleh), by programme for a
 * student, and by person for exam sessions.
 */
class KalenderService
{
    /** How far around the requested range a term is looked for: KRS opens weeks before lectures. */
    private const MARGIN_TERM_BULAN = 3;

    /** Longest span a manual entry may cover; anything longer is a term, not an event. */
    public const MAKS_HARI = 366;

    public function __construct(private readonly JadwalUjianService $ujian) {}

    /*
    |--------------------------------------------------------------------------
    | Reading
    |--------------------------------------------------------------------------
    */

    /**
     * The calendar as one person sees it.
     *
     * A student is held to their own programme whatever the filter says, and
     * only ever sees published entries. A student or lecturer additionally sees
     * their own exam sessions, which nobody else's calendar carries.
     *
     * @param bool $termasukDraf whether unpublished manual entries are included — staff who manage the calendar only
     * @return Collection<int, PeristiwaKalender>
     */
    public function untuk(Authenticatable $aktor, FilterKalender $filter, bool $termasukDraf = false): Collection
    {
        $peran = $this->peran($aktor);
        $terlihat = SasaranPeristiwa::terlihatOleh($peran);

        if ($aktor instanceof Mahasiswa) {
            $filter = $filter->denganProdi($aktor->prodi_id);
            $termasukDraf = false;
        }

        if (!$aktor instanceof Staff) {
            $termasukDraf = false;
        }

        $umum = $this->semua($filter, $termasukDraf)
            ->filter(fn (PeristiwaKalender $p): bool => in_array($p->sasaran, $terlihat, true));

        return $umum
            ->concat($this->pribadi($aktor, $filter))
            ->sortBy([
                fn (PeristiwaKalender $a, PeristiwaKalender $b): int => $a->mulai <=> $b->mulai,
                fn (PeristiwaKalender $a, PeristiwaKalender $b): int => ($a->jamMulai ?? '') <=> ($b->jamMulai ?? ''),
                fn (PeristiwaKalender $a, PeristiwaKalender $b): int => strcmp($a->judul, $b->judul),
            ])
            ->values();
    }

    /**
     * Every shared entry in range that matches the filter, from every source.
     *
     * No audience is applied beyond the filter's own — this is the staff view,
     * and the building block of untuk().
     *
     * @return Collection<int, PeristiwaKalender>
     */
    public function semua(FilterKalender $filter, bool $termasukDraf = false): Collection
    {
        $prodi = Prodi::query()->pluck('nama', 'id')->all();

        $sumber = [
            JenisPeristiwa::Perkuliahan->value => fn () => $this->dariTahunAkademik($filter),
            JenisPeristiwa::Ujian->value => fn () => $this->dariJadwalUjian($filter, $prodi),
            JenisPeristiwa::Edom->value => fn () => $this->dariEdom($filter),
            JenisPeristiwa::Yudisium->value => fn () => $this->dariYudisium($filter, $prodi),
            JenisPeristiwa::Wisuda->value => fn () => $this->dariWisuda($filter),
            JenisPeristiwa::Pmb->value => fn () => $this->dariPmb($filter),
            'manual' => fn () => $this->dariKalender($filter, $termasukDraf),
        ];

        // A filter on one kind skips the queries of every other source. The
        // term row carries four kinds at once, so it is read for any of them.
        $dariTerm = [JenisPeristiwa::Perkuliahan, JenisPeristiwa::Krs, JenisPeristiwa::PerubahanKrs, JenisPeristiwa::Nilai];

        $peristiwa = collect();

        foreach ($sumber as $kunci => $baca) {
            $relevan = match (true) {
                $filter->jenis === null => true,
                $kunci === 'manual' => $filter->jenis->manual(),
                $kunci === JenisPeristiwa::Perkuliahan->value => in_array($filter->jenis, $dariTerm, true),
                default => $filter->jenis->value === $kunci,
            };

            if ($relevan) {
                $peristiwa = $peristiwa->concat($baca());
            }
        }

        return $peristiwa
            ->filter(fn (PeristiwaKalender $p): bool => $p->beririsan($filter->dari, $filter->sampai)
                && ($filter->jenis === null || $p->jenis === $filter->jenis)
                && ($filter->sasaran === null || $p->untukSasaran($filter->sasaran))
                && $p->untukProdi($filter->prodiId))
            ->values();
    }

    /**
     * Weeks of days for a month grid, each day with the entries falling on it.
     *
     * @param Collection<int, PeristiwaKalender> $peristiwa
     * @return list<list<array{tanggal: Carbon, diBulan: bool, hariIni: bool, libur: bool, peristiwa: Collection<int, PeristiwaKalender>}>>
     */
    public function grid(Carbon $bulan, Collection $peristiwa): array
    {
        $filter = FilterKalender::gridBulan($bulan);
        $hari = $filter->dari->copy();
        $minggu = [];
        $urutan = 0;

        while ($hari->lte($filter->sampai)) {
            $padaHari = $peristiwa->filter(fn (PeristiwaKalender $p): bool => $p->jatuhPada($hari))->values();

            $minggu[intdiv($urutan++, 7)][] = [
                'tanggal' => $hari->copy(),
                'diBulan' => $hari->month === $bulan->month,
                'hariIni' => $hari->isToday(),
                'libur' => $padaHari->contains(fn (PeristiwaKalender $p): bool => $p->jenis === JenisPeristiwa::Libur),
                'peristiwa' => $padaHari,
            ];

            $hari->addDay();
        }

        return array_values($minggu);
    }

    /*
    |--------------------------------------------------------------------------
    | Entries written on the calendar itself
    |--------------------------------------------------------------------------
    */

    public function simpan(PeristiwaKalenderData $data): KalenderPeristiwa
    {
        $this->pastikanSah($data);

        return KalenderPeristiwa::create($data->toAttributes())->refresh();
    }

    public function perbarui(KalenderPeristiwa $peristiwa, PeristiwaKalenderData $data): KalenderPeristiwa
    {
        $this->pastikanSah($data);

        $peristiwa->update($data->toAttributes());

        return $peristiwa->refresh();
    }

    /**
     * Removes an entry.
     *
     * Soft, like every other record a reader may already have acted on: a
     * subscribed calendar drops the event on its next poll, and the trail still
     * says it was there and who removed it.
     */
    public function hapus(KalenderPeristiwa $peristiwa): void
    {
        $peristiwa->delete();
    }

    /**
     * The manual entries of one year, for the screen that manages them.
     *
     * @return Collection<int, KalenderPeristiwa>
     */
    public function daftarManual(int $tahun, ?JenisPeristiwa $jenis = null, ?StatusPeristiwa $status = null): Collection
    {
        return KalenderPeristiwa::query()
            ->with('prodi')
            ->beririsan(Carbon::create($tahun, 1, 1), Carbon::create($tahun, 12, 31))
            ->when($jenis !== null, fn ($q) => $q->where('jenis', $jenis->value))
            ->when($status !== null, fn ($q) => $q->where('status', $status->value))
            ->orderBy('tanggal_mulai')
            ->orderBy('judul')
            ->get();
    }

    /*
    |--------------------------------------------------------------------------
    | Sources
    |--------------------------------------------------------------------------
    */

    /**
     * Lectures, KRS, KRS revision and grading, from the term row that gates them.
     *
     * The revision window is shown as what krsDibuka() actually does: open
     * from the day after the fill-in window closes until krs_perubahan_selesai.
     *
     * @return Collection<int, PeristiwaKalender>
     */
    private function dariTahunAkademik(FilterKalender $filter): Collection
    {
        $terms = TahunAkademik::query()
            ->whereDate('tanggal_mulai', '<=', $filter->sampai->copy()->addMonths(self::MARGIN_TERM_BULAN)->toDateString())
            ->whereDate('tanggal_selesai', '>=', $filter->dari->copy()->subMonths(self::MARGIN_TERM_BULAN)->toDateString())
            ->get();

        $peristiwa = collect();

        foreach ($terms as $t) {
            $kunci = 'term-'.$t->kode;

            $peristiwa->push(new PeristiwaKalender(
                kunci: $kunci.'-mulai',
                jenis: JenisPeristiwa::Perkuliahan,
                judul: __('kalender.turunan.perkuliahan_mulai', ['term' => $t->nama]),
                mulai: $t->tanggal_mulai,
                selesai: null,
            ));

            $peristiwa->push(new PeristiwaKalender(
                kunci: $kunci.'-selesai',
                jenis: JenisPeristiwa::Perkuliahan,
                judul: __('kalender.turunan.perkuliahan_selesai', ['term' => $t->nama]),
                mulai: $t->tanggal_selesai,
                selesai: null,
            ));

            if ($t->krs_mulai !== null) {
                $tutup = $t->krs_selesai ?? $t->krs_perubahan_selesai ?? $t->krs_mulai;

                $peristiwa->push(new PeristiwaKalender(
                    kunci: $kunci.'-krs',
                    jenis: JenisPeristiwa::Krs,
                    judul: __('kalender.turunan.krs', ['term' => $t->nama]),
                    mulai: $t->krs_mulai,
                    selesai: $tutup,
                    sasaran: SasaranPeristiwa::Mahasiswa,
                ));

                if ($t->krs_perubahan_selesai !== null && $t->krs_perubahan_selesai->gt($tutup)) {
                    $peristiwa->push(new PeristiwaKalender(
                        kunci: $kunci.'-perubahan-krs',
                        jenis: JenisPeristiwa::PerubahanKrs,
                        judul: __('kalender.turunan.perubahan_krs', ['term' => $t->nama]),
                        mulai: $tutup->copy()->addDay(),
                        selesai: $t->krs_perubahan_selesai,
                        sasaran: SasaranPeristiwa::Mahasiswa,
                    ));
                }
            }

            if ($t->nilai_mulai !== null) {
                $peristiwa->push(new PeristiwaKalender(
                    kunci: $kunci.'-nilai',
                    jenis: JenisPeristiwa::Nilai,
                    judul: __('kalender.turunan.nilai', ['term' => $t->nama]),
                    mulai: $t->nilai_mulai,
                    selesai: $t->nilai_selesai,
                    sasaran: SasaranPeristiwa::Dosen,
                ));
            }
        }

        return $peristiwa;
    }

    /**
     * The UTS and UAS periods, one per term × exam type × programme, from
     * published sessions only — a draft timetable is not a date anybody may
     * plan around.
     *
     * One grouped query. The period is the first to the last published session
     * of the whole group, not of the requested range, so a month view does not
     * show a period cut short at the month's edge.
     *
     * @param array<int, string> $prodi
     * @return Collection<int, PeristiwaKalender>
     */
    private function dariJadwalUjian(FilterKalender $filter, array $prodi): Collection
    {
        $baris = JadwalUjian::query()
            ->join('kelas_kuliah', 'kelas_kuliah.id', '=', 'jadwal_ujian.kelas_kuliah_id')
            ->join('tahun_akademik', 'tahun_akademik.id', '=', 'jadwal_ujian.tahun_akademik_id')
            ->where('jadwal_ujian.status', 'terbit')
            ->whereNull('kelas_kuliah.deleted_at')
            ->groupBy('jadwal_ujian.tahun_akademik_id', 'tahun_akademik.kode', 'tahun_akademik.nama', 'jadwal_ujian.jenis', 'kelas_kuliah.prodi_id')
            // "< the day after" rather than "<= the last day": SQLite keeps the
            // date as "2026-10-31 00:00:00", which sorts after "2026-10-31".
            ->havingRaw('MIN(jadwal_ujian.tanggal) < ? AND MAX(jadwal_ujian.tanggal) >= ?', [
                $filter->sampai->copy()->addDay()->toDateString(),
                $filter->dari->toDateString(),
            ])
            ->selectRaw('tahun_akademik.kode AS term_kode, tahun_akademik.nama AS term_nama, jadwal_ujian.jenis AS jenis, kelas_kuliah.prodi_id AS prodi_id, MIN(jadwal_ujian.tanggal) AS mulai, MAX(jadwal_ujian.tanggal) AS selesai, COUNT(*) AS jumlah')
            ->toBase()
            ->get();

        return $baris->map(function (object $b) use ($prodi): PeristiwaKalender {
            $jenis = JenisUjianKelas::from((string) $b->jenis);
            $prodiId = $b->prodi_id === null ? null : (int) $b->prodi_id;

            return new PeristiwaKalender(
                kunci: 'ujian-'.$b->term_kode.'-'.$jenis->value.'-'.($prodiId ?? 'semua'),
                jenis: JenisPeristiwa::Ujian,
                judul: __('kalender.turunan.ujian', [
                    'jenis' => $jenis->singkatan(),
                    'term' => $b->term_nama,
                ]),
                mulai: (string) $b->mulai,
                selesai: (string) $b->selesai,
                prodiId: $prodiId,
                prodiNama: $prodiId === null ? null : ($prodi[$prodiId] ?? null),
                keterangan: __('kalender.turunan.ujian_keterangan', ['jumlah' => (int) $b->jumlah]),
            );
        });
    }

    /**
     * Evaluation windows the campus has switched on.
     *
     * @return Collection<int, PeristiwaKalender>
     */
    private function dariEdom(FilterKalender $filter): Collection
    {
        return EdomPeriode::query()
            ->aktif()
            ->whereDate('mulai', '<=', $filter->sampai->toDateString())
            ->whereDate('selesai', '>=', $filter->dari->toDateString())
            ->get()
            ->map(fn (EdomPeriode $e): PeristiwaKalender => new PeristiwaKalender(
                kunci: 'edom-'.$e->uuid,
                jenis: JenisPeristiwa::Edom,
                judul: __('kalender.turunan.edom', ['nama' => $e->nama]),
                mulai: $e->mulai,
                selesai: $e->selesai,
                sasaran: SasaranPeristiwa::Mahasiswa,
            ));
    }

    /**
     * Days on which yudisium was decided, per programme, as a count.
     *
     * A yudisium is a decision about one student; the calendar carries only
     * the day and how many, never who. DATE() because the column receives a
     * timestamp when a decision is made, and SQLite keeps the time.
     *
     * @param array<int, string> $prodi
     * @return Collection<int, PeristiwaKalender>
     */
    private function dariYudisium(FilterKalender $filter, array $prodi): Collection
    {
        return Yudisium::query()
            ->join('mahasiswa', 'mahasiswa.id', '=', 'yudisium.mahasiswa_id')
            ->where('yudisium.status', 'ditetapkan')
            ->whereNotNull('yudisium.tanggal_yudisium')
            ->whereDate('yudisium.tanggal_yudisium', '>=', $filter->dari->toDateString())
            ->whereDate('yudisium.tanggal_yudisium', '<=', $filter->sampai->toDateString())
            ->groupByRaw('DATE(yudisium.tanggal_yudisium), mahasiswa.prodi_id')
            ->selectRaw('DATE(yudisium.tanggal_yudisium) AS tanggal, mahasiswa.prodi_id AS prodi_id, COUNT(*) AS jumlah')
            ->toBase()
            ->get()
            ->map(function (object $b) use ($prodi): PeristiwaKalender {
                $prodiId = $b->prodi_id === null ? null : (int) $b->prodi_id;

                return new PeristiwaKalender(
                    kunci: 'yudisium-'.$b->tanggal.'-'.($prodiId ?? 'semua'),
                    jenis: JenisPeristiwa::Yudisium,
                    judul: __('kalender.turunan.yudisium'),
                    mulai: (string) $b->tanggal,
                    selesai: null,
                    prodiId: $prodiId,
                    prodiNama: $prodiId === null ? null : ($prodi[$prodiId] ?? null),
                    keterangan: __('kalender.turunan.yudisium_keterangan', ['jumlah' => (int) $b->jumlah]),
                );
            });
    }

    /** @return Collection<int, PeristiwaKalender> */
    private function dariWisuda(FilterKalender $filter): Collection
    {
        return WisudaPeriode::query()
            ->whereDate('tanggal', '>=', $filter->dari->toDateString())
            ->whereDate('tanggal', '<=', $filter->sampai->toDateString())
            ->get()
            ->map(fn (WisudaPeriode $w): PeristiwaKalender => new PeristiwaKalender(
                kunci: 'wisuda-'.$w->uuid,
                jenis: JenisPeristiwa::Wisuda,
                judul: __('kalender.turunan.wisuda', ['nama' => $w->nama]),
                mulai: $w->tanggal,
                selesai: null,
                lokasi: $w->lokasi,
            ));
    }

    /**
     * Admission waves that are switched on. Staff only: a prospective student
     * reads the admission site, not a portal they have no account on yet.
     *
     * @return Collection<int, PeristiwaKalender>
     */
    private function dariPmb(FilterKalender $filter): Collection
    {
        return PmbGelombang::query()
            ->where('is_active', true)
            ->whereDate('tanggal_mulai', '<=', $filter->sampai->toDateString())
            ->whereDate('tanggal_selesai', '>=', $filter->dari->toDateString())
            ->get()
            ->map(fn (PmbGelombang $g): PeristiwaKalender => new PeristiwaKalender(
                kunci: 'pmb-'.$g->uuid,
                jenis: JenisPeristiwa::Pmb,
                judul: __('kalender.turunan.pmb', ['nama' => $g->nama]),
                mulai: $g->tanggal_mulai,
                selesai: $g->tanggal_selesai,
                sasaran: SasaranPeristiwa::Staf,
            ));
    }

    /** @return Collection<int, PeristiwaKalender> */
    private function dariKalender(FilterKalender $filter, bool $termasukDraf): Collection
    {
        return KalenderPeristiwa::query()
            ->with('prodi')
            ->beririsan($filter->dari, $filter->sampai)
            ->when(!$termasukDraf, fn ($q) => $q->terbit())
            ->get()
            ->map(fn (KalenderPeristiwa $k): PeristiwaKalender => new PeristiwaKalender(
                kunci: 'kalender-'.$k->uuid,
                jenis: $k->jenis,
                judul: $k->judul,
                mulai: $k->tanggal_mulai,
                selesai: $k->tanggal_selesai,
                sasaran: $k->sasaran,
                prodiId: $k->prodi_id,
                prodiNama: $k->prodi?->nama,
                keterangan: $k->keterangan,
                status: $k->status,
                uuid: $k->uuid,
            ));
    }

    /**
     * A student's or lecturer's own published exam sessions, timed, with the
     * room — through JadwalUjianService, so the rule for "whose session is
     * this" is the exam module's and not a second copy here.
     *
     * @return Collection<int, PeristiwaKalender>
     */
    private function pribadi(Authenticatable $aktor, FilterKalender $filter): Collection
    {
        if (!$aktor instanceof Mahasiswa && !$aktor instanceof Dosen) {
            return collect();
        }

        if ($filter->jenis !== null && $filter->jenis !== JenisPeristiwa::Ujian) {
            return collect();
        }

        $terms = TahunAkademik::query()
            ->whereIn('id', JadwalUjian::query()
                ->terbit()
                ->dalamRentang($filter->dari, $filter->sampai)
                ->select('tahun_akademik_id'))
            ->get();

        $peristiwa = collect();

        foreach ($terms as $term) {
            $sesi = $aktor instanceof Mahasiswa
                ? $this->ujian->untukMahasiswa($aktor, $term)
                : $this->ujian->untukDosen($aktor, $term);

            $sesi = $sesi->filter(fn (JadwalUjian $j): bool => $j->tanggal->betweenIncluded($filter->dari, $filter->sampai))->values();

            $ruangDuduk = $aktor instanceof Mahasiswa ? $this->ujian->ruangUntukBanyak($sesi, $aktor) : [];

            foreach ($sesi as $j) {
                $ruang = $aktor instanceof Mahasiswa
                    ? ($ruangDuduk[$j->id] ?? null)?->kode
                    : $j->ruang->map(fn ($r) => $r->ruang?->kode)->filter()->implode(', ');

                $peristiwa->push(new PeristiwaKalender(
                    kunci: 'sesi-ujian-'.$j->uuid,
                    jenis: JenisPeristiwa::Ujian,
                    judul: $j->jenis->singkatan().' · '.$j->kelasKuliah->mataKuliah->nama,
                    mulai: $j->tanggal,
                    selesai: null,
                    sasaran: $aktor instanceof Mahasiswa ? SasaranPeristiwa::Mahasiswa : SasaranPeristiwa::Dosen,
                    keterangan: __('kalender.turunan.sesi_keterangan', [
                        'kode' => $j->kelasKuliah->mataKuliah->kode,
                        'kelas' => $j->kelasKuliah->kode,
                    ]),
                    jamMulai: JadwalUjian::jam($j->jam_mulai),
                    jamSelesai: JadwalUjian::jam($j->jam_selesai),
                    lokasi: $ruang !== '' ? $ruang : null,
                    pribadi: true,
                ));
            }
        }

        return $peristiwa;
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    private function pastikanSah(PeristiwaKalenderData $data): void
    {
        if (!$data->jenis->manual()) {
            // A KRS window typed in here would be a second KRS window, one that
            // gates nothing. The term screen owns that date.
            throw new AturanAkademikException(__('kalender.galat.jenis_turunan', ['jenis' => $data->jenis->label()]));
        }

        $mulai = Carbon::parse($data->tanggalMulai);
        $selesai = Carbon::parse($data->tanggalSelesai);

        if ($selesai->lt($mulai)) {
            throw new AturanAkademikException(__('kalender.galat.rentang'));
        }

        if ($mulai->diffInDays($selesai) >= self::MAKS_HARI) {
            throw new AturanAkademikException(__('kalender.galat.terlalu_panjang', ['hari' => self::MAKS_HARI]));
        }
    }

    private function peran(Authenticatable $aktor): UserRole
    {
        return match (true) {
            $aktor instanceof Staff => UserRole::Staff,
            $aktor instanceof Dosen => UserRole::Dosen,
            $aktor instanceof Mahasiswa => UserRole::Mahasiswa,
            default => throw new \InvalidArgumentException('Unknown calendar reader.'),
        };
    }
}

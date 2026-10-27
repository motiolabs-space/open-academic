<?php

declare(strict_types=1);

namespace App\Services\Akademik;

use App\DTOs\Akademik\FilterPemanfaatanRuang;
use App\DTOs\Akademik\HasilRuangKosong;
use App\DTOs\Akademik\KelebihanPeserta;
use App\DTOs\Akademik\KriteriaRuangKosong;
use App\DTOs\Akademik\LaporanPemanfaatanRuang;
use App\DTOs\Akademik\PemanfaatanRuang;
use App\Enums\KrsStatus;
use App\Enums\StatusJadwalUjian;
use App\Models\Akademik\JadwalKuliah;
use App\Models\Akademik\KalenderPeristiwa;
use App\Models\Akademik\Ruang;
use App\Models\Akademik\TahunAkademik;
use App\Support\Format;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * How the campus's rooms are used, read from the timetables that use them.
 *
 * A report and nothing more: there is no booking here, and nothing in this
 * class writes. Use is what the class timetable and the *published* exam
 * timetable of a term put in a room; availability is the campus's opening
 * hours (config/ruang.php) on every operating day of the term that is not a
 * campus-wide holiday.
 *
 * The term is walked day by day rather than multiplied out from one week,
 * because the days that differ are the point: a holiday takes a day out of
 * both sides of the ratio, a programme's own holiday takes only its classes
 * out, and an exam fortnight puts sessions where the weekly timetable has
 * none. Days that do not differ are computed once per weekday and reused, so
 * the walk costs a handful of array operations per room, not a query.
 *
 * Every figure comes from six queries, whatever the number of rooms, slots,
 * or sessions.
 */
class PemanfaatanRuangService
{
    /*
    |--------------------------------------------------------------------------
    | The report
    |--------------------------------------------------------------------------
    */

    public function laporan(FilterPemanfaatanRuang $filter): LaporanPemanfaatanRuang
    {
        $term = $filter->term;
        $jendela = $this->jendela();

        $ruang = $this->ruang($filter->gedungId, $filter->jenis, aktifSaja: false);
        $kuliah = $this->slotKuliah($term, $ruang->keys()->all());
        $ujian = $this->ruangUjian($term);

        $peserta = $this->pesertaPerKelas(
            $term,
            $kuliah->pluck('kelas_id')->merge($ujian->pluck('kelas_id'))->unique()->values()->all(),
        );

        $kuliahPerRuang = $this->kuliahPerRuangHari($kuliah, $peserta);
        $ujianPerRuang = $this->ujianPerRuangTanggal($ujian, $peserta, $ruang);

        $libur = KalenderPeristiwa::tanggalLibur($term->tanggal_mulai, $term->tanggal_selesai);
        $tanggalan = $this->tanggalan($term, $jendela, $libur);
        $jumlahMinggu = max(1, count($tanggalan)) / 7;
        $ambang = (int) config('ruang.ambang_jarang_persen');

        $kelebihan = $this->kelebihan($kuliah, $peserta, $ujianPerRuang, $ruang);
        $kelebihanPerRuang = $kelebihan->countBy('ruangId');

        $hasil = collect();

        foreach ($ruang as $r) {
            $hitung = $this->hitungRuang(
                $r,
                $kuliahPerRuang[$r->id] ?? [],
                $ujianPerRuang[$r->id] ?? [],
                $tanggalan,
                $libur,
                $jendela,
            );

            $slotKuliah = array_sum(array_map('count', $kuliahPerRuang[$r->id] ?? []));
            $sesiUjian = count(array_merge(...array_values($ujianPerRuang[$r->id] ?? [[]])));

            // A retired room stays in the report only while something still
            // uses it — that is exactly the row somebody needs to see.
            if (!$r->is_active && $slotKuliah === 0 && $sesiUjian === 0) {
                continue;
            }

            $tersedia = $hitung['tersedia'] / 60 / $jumlahMinggu;
            $terpakai = $hitung['terpakai'] / 60 / $jumlahMinggu;

            $hasil->push(new PemanfaatanRuang(
                ruangId: $r->id,
                uuid: $r->uuid,
                kode: $r->kode,
                nama: $r->nama,
                jenis: (string) $r->jenis,
                kapasitas: (int) $r->kapasitas,
                aktif: (bool) $r->is_active,
                gedungKode: $r->gedung?->kode,
                gedungNama: $r->gedung?->nama,
                jamTersediaPerMinggu: $tersedia,
                jamTerpakaiPerMinggu: $terpakai,
                jamDiLuarPerMinggu: $hitung['luar'] / 60 / $jumlahMinggu,
                okupansiPersen: $hitung['ditawarkan'] > 0 ? $hitung['terisi'] / $hitung['ditawarkan'] * 100 : null,
                slotKuliah: $slotKuliah,
                sesiUjian: $sesiUjian,
                kelebihan: (int) ($kelebihanPerRuang[$r->id] ?? 0),
                jarang: $r->is_active && $tersedia > 0 && $terpakai / $tersedia * 100 < $ambang,
                petaPanas: $hitung['peta'],
            ));
        }

        return new LaporanPemanfaatanRuang(
            filter: $filter,
            ruang: $this->urutkan($hasil, $filter->urut),
            kelebihan: $kelebihan->sortByDesc(fn (KelebihanPeserta $k): int => $k->selisih())->values(),
            hariOperasional: $jendela['hari'],
            kolomPeta: $jendela['kolom'],
            langkahPeta: $jendela['langkah'],
            jamMulai: $jendela['jamMulai'],
            jamSelesai: $jendela['jamSelesai'],
            jumlahMinggu: $jumlahMinggu,
            hariLibur: count(array_filter($tanggalan, fn (array $t): bool => $t['buka'] === false && $t['operasional'])),
            ambangJarang: $ambang,
        );
    }

    /** The report as a spreadsheet: one row per room, raw numbers. */
    public function ekspor(LaporanPemanfaatanRuang $laporan): StreamedResponse
    {
        $nama = sprintf('pemanfaatan-ruang-%s.csv', $laporan->filter->term->kode);
        $bom = (bool) config('ruang.ekspor.bom_utf8');

        return response()->streamDownload(function () use ($laporan, $bom): void {
            $keluaran = fopen('php://output', 'wb');

            if ($bom) {
                fwrite($keluaran, "\xEF\xBB\xBF");
            }

            fputcsv($keluaran, array_values(__('pemanfaatan_ruang.csv')), ',', '"', '\\');

            foreach ($laporan->ruang as $r) {
                fputcsv($keluaran, [
                    $r->gedungKode ?? '',
                    $r->kode,
                    $r->nama,
                    __('pemanfaatan_ruang.jenis_ruang.'.$r->jenis),
                    $r->kapasitas,
                    round($r->jamTersediaPerMinggu, 2),
                    round($r->jamTerpakaiPerMinggu, 2),
                    round($r->jamKosongPerMinggu(), 2),
                    $r->pemanfaatanPersen() === null ? '' : round($r->pemanfaatanPersen(), 1),
                    $r->okupansiPersen === null ? '' : round($r->okupansiPersen, 1),
                    round($r->jamDiLuarPerMinggu, 2),
                    $r->slotKuliah,
                    $r->sesiUjian,
                    $r->kelebihan,
                    $r->jarang ? 'ya' : 'tidak',
                ], ',', '"', '\\');
            }

            fclose($keluaran);
        }, $nama, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /*
    |--------------------------------------------------------------------------
    | Free rooms
    |--------------------------------------------------------------------------
    */

    /**
     * Rooms seating at least the minimum that nothing occupies in the window.
     *
     * Read-only, deliberately: the answer is a starting point for whoever is
     * writing the timetable, and the timetable's own clash check is what
     * actually holds the room.
     */
    public function cariKosong(KriteriaRuangKosong $kriteria): HasilRuangKosong
    {
        $jendela = $this->jendela();
        $mulai = self::menit($kriteria->jamMulai);
        $selesai = self::menit($kriteria->jamSelesai);

        $ruang = $this->ruang($kriteria->gedungId, $kriteria->jenis, aktifSaja: true)
            ->filter(fn (Ruang $r): bool => (int) $r->kapasitas >= $kriteria->kapasitasMinimum)
            ->sortBy([['kapasitas', 'asc'], ['kode', 'asc']]);

        $libur = [];
        $liburKampus = null;
        $kuliahBerjalan = true;

        if ($kriteria->tanggal !== null) {
            $libur = KalenderPeristiwa::tanggalLibur($kriteria->tanggal, $kriteria->tanggal);
            $liburKampus = KalenderPeristiwa::liburUntuk($libur, $kriteria->tanggal->toDateString(), null);

            // Weekly slots only recur inside the term, and not on a day the
            // whole campus is off.
            $kuliahBerjalan = $liburKampus === null
                && $kriteria->tanggal->betweenIncluded($kriteria->term->tanggal_mulai, $kriteria->term->tanggal_selesai);
        }

        $oleh = [];
        $ids = $ruang->keys()->all();

        if ($kuliahBerjalan && $ids !== []) {
            foreach ($this->slotKuliah($kriteria->term, $ids, $kriteria->hari) as $s) {
                if (!self::beririsan($mulai, $selesai, self::menit($s->jam_mulai), self::menit($s->jam_selesai))) {
                    continue;
                }

                if ($kriteria->tanggal !== null
                    && KalenderPeristiwa::liburUntuk($libur, $kriteria->tanggal->toDateString(), (int) $s->prodi_id) !== null) {
                    continue;
                }

                $oleh[$s->ruang_id][] = sprintf('%s — Kelas %s · %s', $s->mk_nama, $s->kelas_kode, Format::rentangJam($s->jam_mulai, $s->jam_selesai));
            }
        }

        if ($kriteria->tanggal !== null && $ids !== []) {
            foreach ($this->ujianPadaTanggal($kriteria->tanggal, $ids) as $u) {
                if (!self::beririsan($mulai, $selesai, self::menit($u->jam_mulai), self::menit($u->jam_selesai))) {
                    continue;
                }

                $oleh[$u->ruang_id][] = sprintf(
                    '%s · %s — Kelas %s · %s',
                    __('jadwal_ujian.jenis_singkat.'.$u->jenis),
                    $u->mk_nama,
                    $u->kelas_kode,
                    Format::rentangJam($u->jam_mulai, $u->jam_selesai),
                );
            }
        }

        return new HasilRuangKosong(
            kriteria: $kriteria,
            kosong: $ruang->reject(fn (Ruang $r): bool => isset($oleh[$r->id]))->values(),
            terpakai: $ruang->filter(fn (Ruang $r): bool => isset($oleh[$r->id]))
                ->map(fn (Ruang $r): array => ['ruang' => $r, 'oleh' => $oleh[$r->id]])
                ->values(),
            libur: $liburKampus,
            diLuarOperasional: !in_array($kriteria->hari, $jendela['hari'], true)
                || $mulai < $jendela['mulai']
                || $selesai > $jendela['selesai'],
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Reading the timetables — one query each
    |--------------------------------------------------------------------------
    */

    /**
     * Rooms in scope, keyed by id, with their building.
     *
     * @return Collection<int, Ruang>
     */
    private function ruang(?int $gedungId, ?string $jenis, bool $aktifSaja): Collection
    {
        return Ruang::query()
            ->with('gedung')
            ->when($aktifSaja, fn ($q) => $q->aktif())
            ->when($gedungId !== null, fn ($q) => $q->where('gedung_id', $gedungId))
            ->when($jenis !== null, fn ($q) => $q->where('jenis', $jenis))
            ->orderBy('kode')
            ->get()
            ->keyBy('id');
    }

    /**
     * Weekly class slots of the term placed in these rooms.
     *
     * @param array<int, int> $ruangIds
     * @return Collection<int, object>
     */
    private function slotKuliah(TahunAkademik $term, array $ruangIds, ?int $hari = null): Collection
    {
        if ($ruangIds === []) {
            return collect();
        }

        return DB::table('jadwal_kuliah')
            ->join('kelas_kuliah', 'kelas_kuliah.id', '=', 'jadwal_kuliah.kelas_kuliah_id')
            ->join('mata_kuliah', 'mata_kuliah.id', '=', 'kelas_kuliah.mata_kuliah_id')
            ->where('kelas_kuliah.tahun_akademik_id', $term->id)
            ->whereIn('jadwal_kuliah.ruang_id', $ruangIds)
            ->when($hari !== null, fn ($q) => $q->where('jadwal_kuliah.hari', $hari))
            ->whereNull('jadwal_kuliah.deleted_at')
            ->whereNull('kelas_kuliah.deleted_at')
            ->orderBy('jadwal_kuliah.hari')
            ->orderBy('jadwal_kuliah.jam_mulai')
            ->get([
                'jadwal_kuliah.ruang_id', 'jadwal_kuliah.hari', 'jadwal_kuliah.jam_mulai', 'jadwal_kuliah.jam_selesai',
                'kelas_kuliah.id as kelas_id', 'kelas_kuliah.prodi_id', 'kelas_kuliah.kode as kelas_kode',
                'mata_kuliah.nama as mk_nama',
            ]);
    }

    /**
     * Every room of every published exam session of the term, in seating order.
     *
     * Not narrowed to the rooms in scope: seats are filled across a session's
     * rooms in order, so how many sit in the second room depends on the first
     * even when the first is in another building.
     *
     * @return Collection<int, object>
     */
    private function ruangUjian(TahunAkademik $term): Collection
    {
        return DB::table('jadwal_ujian_ruang')
            ->join('jadwal_ujian', 'jadwal_ujian.id', '=', 'jadwal_ujian_ruang.jadwal_ujian_id')
            ->join('kelas_kuliah', 'kelas_kuliah.id', '=', 'jadwal_ujian.kelas_kuliah_id')
            ->join('mata_kuliah', 'mata_kuliah.id', '=', 'kelas_kuliah.mata_kuliah_id')
            ->where('jadwal_ujian.tahun_akademik_id', $term->id)
            ->where('jadwal_ujian.status', StatusJadwalUjian::Terbit->value)
            ->whereNull('jadwal_ujian.deleted_at')
            ->orderBy('jadwal_ujian.id')
            ->orderBy('jadwal_ujian_ruang.urutan')
            ->orderBy('jadwal_ujian_ruang.id')
            ->get([
                'jadwal_ujian_ruang.ruang_id', 'jadwal_ujian_ruang.kursi',
                'jadwal_ujian.id as ujian_id', 'jadwal_ujian.jenis', 'jadwal_ujian.tanggal',
                'jadwal_ujian.jam_mulai', 'jadwal_ujian.jam_selesai',
                'kelas_kuliah.id as kelas_id', 'kelas_kuliah.kode as kelas_kode', 'mata_kuliah.nama as mk_nama',
            ]);
    }

    /**
     * Published exam sessions on one date in these rooms, from any term.
     *
     * @param array<int, int> $ruangIds
     * @return Collection<int, object>
     */
    private function ujianPadaTanggal(Carbon $tanggal, array $ruangIds): Collection
    {
        return DB::table('jadwal_ujian_ruang')
            ->join('jadwal_ujian', 'jadwal_ujian.id', '=', 'jadwal_ujian_ruang.jadwal_ujian_id')
            ->join('kelas_kuliah', 'kelas_kuliah.id', '=', 'jadwal_ujian.kelas_kuliah_id')
            ->join('mata_kuliah', 'mata_kuliah.id', '=', 'kelas_kuliah.mata_kuliah_id')
            ->whereIn('jadwal_ujian_ruang.ruang_id', $ruangIds)
            ->whereDate('jadwal_ujian.tanggal', $tanggal->toDateString())
            ->where('jadwal_ujian.status', StatusJadwalUjian::Terbit->value)
            ->whereNull('jadwal_ujian.deleted_at')
            ->get([
                'jadwal_ujian_ruang.ruang_id', 'jadwal_ujian.jenis', 'jadwal_ujian.jam_mulai', 'jadwal_ujian.jam_selesai',
                'kelas_kuliah.kode as kelas_kode', 'mata_kuliah.nama as mk_nama',
            ]);
    }

    /**
     * Students on an approved, live study plan of the term, per class.
     *
     * The same reading of "enrolled" as the exam timetable's: a draft or a
     * submitted plan is not yet a person in a seat.
     *
     * @param array<int, int> $kelasIds
     * @return array<int, int> kelas id => headcount
     */
    private function pesertaPerKelas(TahunAkademik $term, array $kelasIds): array
    {
        if ($kelasIds === []) {
            return [];
        }

        return DB::table('krs_detail')
            ->join('krs', 'krs.id', '=', 'krs_detail.krs_id')
            ->whereIn('krs_detail.kelas_kuliah_id', $kelasIds)
            ->where('krs.tahun_akademik_id', $term->id)
            ->where('krs.status', KrsStatus::Disetujui->value)
            ->whereNull('krs_detail.deleted_at')
            ->whereNull('krs.deleted_at')
            ->groupBy('krs_detail.kelas_kuliah_id')
            ->selectRaw('krs_detail.kelas_kuliah_id as kelas_id, COUNT(DISTINCT krs.mahasiswa_id) as jumlah')
            ->pluck('jumlah', 'kelas_id')
            ->map(fn ($n): int => (int) $n)
            ->all();
    }

    /*
    |--------------------------------------------------------------------------
    | Shaping
    |--------------------------------------------------------------------------
    */

    /**
     * @param Collection<int, object> $kuliah
     * @param array<int, int> $peserta
     * @return array<int, array<int, list<array{mulai: int, selesai: int, peserta: int, prodi_id: int}>>> ruang => ISO weekday => uses
     */
    private function kuliahPerRuangHari(Collection $kuliah, array $peserta): array
    {
        $hasil = [];

        foreach ($kuliah as $s) {
            $mulai = self::menit($s->jam_mulai);
            $selesai = self::menit($s->jam_selesai);

            if ($selesai <= $mulai) {
                continue;
            }

            $hasil[(int) $s->ruang_id][(int) $s->hari][] = [
                'mulai' => $mulai,
                'selesai' => $selesai,
                'peserta' => $peserta[(int) $s->kelas_id] ?? 0,
                'prodi_id' => (int) $s->prodi_id,
            ];
        }

        return $hasil;
    }

    /**
     * Exam rooms in scope by date, with the students actually seated in each.
     *
     * Seats are filled room by room in the session's order, as the exam card
     * does: a session of 50 split 40 + 40 puts 40 in the first room and 10 in
     * the second, not 25 in each.
     *
     * @param Collection<int, object> $ujian
     * @param array<int, int> $peserta
     * @param Collection<int, Ruang> $ruang
     * @return array<int, array<string, list<array{mulai: int, selesai: int, peserta: int, jenis: string, kelas: string, waktu: string}>>> ruang => Y-m-d => sessions
     */
    private function ujianPerRuangTanggal(Collection $ujian, array $peserta, Collection $ruang): array
    {
        $hasil = [];

        foreach ($ujian->groupBy('ujian_id') as $baris) {
            $sisa = $peserta[(int) $baris->first()->kelas_id] ?? 0;

            foreach ($baris as $u) {
                $duduk = min((int) $u->kursi, $sisa);
                $sisa -= $duduk;

                if (!$ruang->has((int) $u->ruang_id)) {
                    continue;
                }

                $tanggal = substr((string) $u->tanggal, 0, 10);

                $hasil[(int) $u->ruang_id][$tanggal][] = [
                    'mulai' => self::menit($u->jam_mulai),
                    'selesai' => self::menit($u->jam_selesai),
                    'peserta' => $duduk,
                    'jenis' => (string) $u->jenis,
                    'kelas' => $u->mk_nama.' — Kelas '.$u->kelas_kode,
                    'waktu' => Format::tanggal($tanggal).' · '.Format::rentangJam($u->jam_mulai, $u->jam_selesai),
                ];
            }
        }

        return $hasil;
    }

    /**
     * Every day of the term, and whether its rooms are available.
     *
     * @param array{hari: list<int>} $jendela
     * @param array<string, list<array{judul: string, prodi_id: ?int}>> $libur
     * @return list<array{kunci: string, hari: int, operasional: bool, buka: bool, libur: bool, adaLibur: bool}>
     */
    private function tanggalan(TahunAkademik $term, array $jendela, array $libur): array
    {
        $hasil = [];
        $tanggal = $term->tanggal_mulai->copy()->startOfDay();
        $akhir = $term->tanggal_selesai->copy()->startOfDay();

        while ($tanggal->lte($akhir)) {
            $kunci = $tanggal->toDateString();
            $operasional = in_array($tanggal->dayOfWeekIso, $jendela['hari'], true);
            $liburKampus = KalenderPeristiwa::liburUntuk($libur, $kunci, null) !== null;

            $hasil[] = [
                'kunci' => $kunci,
                'hari' => $tanggal->dayOfWeekIso,
                'operasional' => $operasional,
                'buka' => $operasional && !$liburKampus,
                'libur' => $liburKampus,
                'adaLibur' => isset($libur[$kunci]),
            ];

            $tanggal->addDay();
        }

        return $hasil;
    }

    /**
     * The term's totals for one room, in minutes and seat-minutes.
     *
     * @param array<int, list<array{mulai: int, selesai: int, peserta: int, prodi_id: int}>> $kuliah
     * @param array<string, list<array{mulai: int, selesai: int, peserta: int}>> $ujian
     * @param list<array{kunci: string, hari: int, operasional: bool, buka: bool, libur: bool, adaLibur: bool}> $tanggalan
     * @param array<string, list<array{judul: string, prodi_id: ?int}>> $libur
     * @param array{hari: list<int>, mulai: int, selesai: int, langkah: int, kolom: list<int>} $jendela
     * @return array{tersedia: int, terpakai: int, luar: int, terisi: int, ditawarkan: int, peta: array<int, array<int, ?float>>}
     */
    private function hitungRuang(Ruang $ruang, array $kuliah, array $ujian, array $tanggalan, array $libur, array $jendela): array
    {
        $kapasitas = (int) $ruang->kapasitas;
        $total = ['tersedia' => 0, 'terpakai' => 0, 'luar' => 0, 'terisi' => 0, 'ditawarkan' => 0];
        $petaMenit = [];
        $petaHari = [];
        $polos = [];

        foreach ($tanggalan as $t) {
            $ujianHari = $ujian[$t['kunci']] ?? [];

            if (!$t['adaLibur'] && $ujianHari === []) {
                // An ordinary day: the same for every week, computed once.
                $hari = $polos[$t['hari']] ??= $this->satuHari($kuliah[$t['hari']] ?? [], $t['buka'], $kapasitas, $jendela);
            } else {
                $pakai = $t['libur'] ? [] : array_values(array_filter(
                    $kuliah[$t['hari']] ?? [],
                    fn (array $k): bool => KalenderPeristiwa::liburUntuk($libur, $t['kunci'], $k['prodi_id']) === null,
                ));

                $hari = $this->satuHari([...$pakai, ...$ujianHari], $t['buka'], $kapasitas, $jendela);
            }

            foreach (['terpakai', 'luar', 'terisi', 'ditawarkan'] as $kunci) {
                $total[$kunci] += $hari[$kunci];
            }

            if ($t['buka']) {
                $total['tersedia'] += $jendela['selesai'] - $jendela['mulai'];
                $petaHari[$t['hari']] = ($petaHari[$t['hari']] ?? 0) + 1;

                foreach ($hari['peta'] as $kolom => $menit) {
                    $petaMenit[$t['hari']][$kolom] = ($petaMenit[$t['hari']][$kolom] ?? 0) + $menit;
                }
            }
        }

        $peta = [];

        foreach ($jendela['hari'] as $h) {
            foreach ($jendela['kolom'] as $kolom) {
                $lebar = min($kolom + $jendela['langkah'], $jendela['selesai']) - $kolom;
                $peta[$h][$kolom] = isset($petaHari[$h])
                    ? ($petaMenit[$h][$kolom] ?? 0) / ($lebar * $petaHari[$h])
                    : null;
            }
        }

        return [...$total, 'peta' => $peta];
    }

    /**
     * One day in one room.
     *
     * Occupied time is the union of the uses, so a lecture and an exam on top
     * of each other (or two classes double-booked) count once. Seat use is not
     * merged: each use filled its own seats for its own minutes.
     *
     * @param list<array{mulai: int, selesai: int, peserta: int}> $pakai
     * @param array{mulai: int, selesai: int, langkah: int, kolom: list<int>} $jendela
     * @return array{terpakai: int, luar: int, terisi: int, ditawarkan: int, peta: array<int, int>}
     */
    private function satuHari(array $pakai, bool $buka, int $kapasitas, array $jendela): array
    {
        $hasil = ['terpakai' => 0, 'luar' => 0, 'terisi' => 0, 'ditawarkan' => 0, 'peta' => []];

        if ($pakai === []) {
            return $hasil;
        }

        if ($kapasitas > 0) {
            foreach ($pakai as $p) {
                $durasi = $p['selesai'] - $p['mulai'];
                $hasil['terisi'] += $p['peserta'] * $durasi;
                $hasil['ditawarkan'] += $kapasitas * $durasi;
            }
        }

        $gabung = self::gabung($pakai);
        $semua = array_sum(array_map(fn (array $g): int => $g[1] - $g[0], $gabung));

        if (!$buka) {
            $hasil['luar'] = $semua;

            return $hasil;
        }

        $hasil['terpakai'] = self::irisan($gabung, $jendela['mulai'], $jendela['selesai']);
        $hasil['luar'] = $semua - $hasil['terpakai'];

        foreach ($jendela['kolom'] as $kolom) {
            $hasil['peta'][$kolom] = self::irisan($gabung, $kolom, min($kolom + $jendela['langkah'], $jendela['selesai']));
        }

        return $hasil;
    }

    /**
     * @param Collection<int, object> $kuliah
     * @param array<int, int> $peserta
     * @param array<int, array<string, list<array{peserta: int, jenis: string, kelas: string, waktu: string}>>> $ujian
     * @param Collection<int, Ruang> $ruang
     * @return Collection<int, KelebihanPeserta>
     */
    private function kelebihan(Collection $kuliah, array $peserta, array $ujian, Collection $ruang): Collection
    {
        $hasil = collect();

        foreach ($kuliah as $s) {
            $r = $ruang->get((int) $s->ruang_id);
            $jumlah = $peserta[(int) $s->kelas_id] ?? 0;

            if ($r === null || (int) $r->kapasitas === 0 || $jumlah <= (int) $r->kapasitas) {
                continue;
            }

            $hasil->push(new KelebihanPeserta(
                ruangId: $r->id,
                ruangKode: $r->kode,
                kelas: $s->mk_nama.' — Kelas '.$s->kelas_kode,
                jenis: 'kuliah',
                waktu: (JadwalKuliah::HARI[(int) $s->hari] ?? '—').', '.Format::rentangJam($s->jam_mulai, $s->jam_selesai),
                peserta: $jumlah,
                kapasitas: (int) $r->kapasitas,
            ));
        }

        foreach ($ujian as $ruangId => $perTanggal) {
            $r = $ruang->get($ruangId);

            if ($r === null || (int) $r->kapasitas === 0) {
                continue;
            }

            foreach (array_merge(...array_values($perTanggal)) as $u) {
                if ($u['peserta'] > (int) $r->kapasitas) {
                    $hasil->push(new KelebihanPeserta(
                        ruangId: $r->id,
                        ruangKode: $r->kode,
                        kelas: $u['kelas'],
                        jenis: $u['jenis'],
                        waktu: $u['waktu'],
                        peserta: $u['peserta'],
                        kapasitas: (int) $r->kapasitas,
                    ));
                }
            }
        }

        return $hasil;
    }

    /**
     * @param Collection<int, PemanfaatanRuang> $ruang
     * @return Collection<int, PemanfaatanRuang>
     */
    private function urutkan(Collection $ruang, string $urut): Collection
    {
        // Rooms with no available hours have no percentage; they go last
        // either way rather than posing as the emptiest or the fullest.
        $persen = fn (PemanfaatanRuang $r): float => $r->pemanfaatanPersen() ?? -1.0;

        $urutan = match ($urut) {
            FilterPemanfaatanRuang::URUT_TERPENUH => $ruang->sortBy([
                fn (PemanfaatanRuang $a, PemanfaatanRuang $b): int => $persen($b) <=> $persen($a),
                fn (PemanfaatanRuang $a, PemanfaatanRuang $b): int => $a->kode <=> $b->kode,
            ]),
            FilterPemanfaatanRuang::URUT_TERKOSONG => $ruang->sortBy([
                fn (PemanfaatanRuang $a, PemanfaatanRuang $b): int => ($a->pemanfaatanPersen() === null) <=> ($b->pemanfaatanPersen() === null),
                fn (PemanfaatanRuang $a, PemanfaatanRuang $b): int => $persen($a) <=> $persen($b),
                fn (PemanfaatanRuang $a, PemanfaatanRuang $b): int => $a->kode <=> $b->kode,
            ]),
            default => $ruang->sortBy([
                fn (PemanfaatanRuang $a, PemanfaatanRuang $b): int => (string) $a->gedungKode <=> (string) $b->gedungKode,
                fn (PemanfaatanRuang $a, PemanfaatanRuang $b): int => $a->kode <=> $b->kode,
            ]),
        };

        return $urutan->values();
    }

    /*
    |--------------------------------------------------------------------------
    | Time arithmetic
    |--------------------------------------------------------------------------
    */

    /**
     * The campus's opening frame, in minutes after midnight.
     *
     * @return array{hari: list<int>, mulai: int, selesai: int, langkah: int, kolom: list<int>, jamMulai: string, jamSelesai: string}
     */
    private function jendela(): array
    {
        $jamMulai = substr((string) config('ruang.operasional.jam_mulai'), 0, 5);
        $jamSelesai = substr((string) config('ruang.operasional.jam_selesai'), 0, 5);
        $mulai = self::menit($jamMulai);
        $selesai = max($mulai, self::menit($jamSelesai));
        $langkah = max(15, (int) config('ruang.langkah_peta_menit'));

        $hari = array_values(array_unique(array_map('intval', (array) config('ruang.operasional.hari'))));
        sort($hari);

        return [
            'hari' => $hari,
            'mulai' => $mulai,
            'selesai' => $selesai,
            'langkah' => $langkah,
            'kolom' => $selesai > $mulai ? range($mulai, $selesai - 1, $langkah) : [],
            'jamMulai' => $jamMulai,
            'jamSelesai' => $jamSelesai,
        ];
    }

    /**
     * "07:30", "07:30:00" → 450.
     *
     * Parsed rather than compared as strings: MySQL hands a TIME back with
     * seconds and SQLite hands back what it was given, and "10:00" < "10:00:00"
     * as strings turns two consecutive classes into an overlap.
     */
    public static function menit(?string $jam): int
    {
        [$h, $m] = array_pad(explode(':', substr((string) $jam, 0, 5)), 2, '0');

        return (int) $h * 60 + (int) $m;
    }

    /** Touching ends do not overlap: 08:00–10:00 and 10:00–12:00 are consecutive. */
    private static function beririsan(int $mulaiA, int $selesaiA, int $mulaiB, int $selesaiB): bool
    {
        return $mulaiA < $selesaiB && $mulaiB < $selesaiA;
    }

    /**
     * @param list<array{mulai: int, selesai: int}> $pakai
     * @return list<array{0: int, 1: int}> disjoint, sorted
     */
    private static function gabung(array $pakai): array
    {
        $rentang = array_map(fn (array $p): array => [$p['mulai'], $p['selesai']], $pakai);
        usort($rentang, fn (array $a, array $b): int => $a[0] <=> $b[0]);

        $hasil = [];

        foreach ($rentang as [$mulai, $selesai]) {
            $akhir = count($hasil) - 1;

            if ($akhir >= 0 && $mulai <= $hasil[$akhir][1]) {
                $hasil[$akhir][1] = max($hasil[$akhir][1], $selesai);
            } else {
                $hasil[] = [$mulai, $selesai];
            }
        }

        return $hasil;
    }

    /** @param list<array{0: int, 1: int}> $gabung */
    private static function irisan(array $gabung, int $dari, int $sampai): int
    {
        $menit = 0;

        foreach ($gabung as [$mulai, $selesai]) {
            $menit += max(0, min($selesai, $sampai) - max($mulai, $dari));
        }

        return $menit;
    }
}

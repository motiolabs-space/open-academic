<?php

declare(strict_types=1);

use App\DTOs\Akademik\FilterPemanfaatanRuang;
use App\DTOs\Akademik\KriteriaRuangKosong;
use App\DTOs\Akademik\PemanfaatanRuang;
use App\Enums\JenisPeristiwa;
use App\Enums\JenisUjianKelas;
use App\Enums\KrsStatus;
use App\Enums\SasaranPeristiwa;
use App\Enums\SemesterType;
use App\Enums\StatusJadwalUjian;
use App\Enums\StatusPeristiwa;
use App\Enums\StudentStatus;
use App\Models\Akademik\Gedung;
use App\Models\Akademik\JadwalKuliah;
use App\Models\Akademik\JadwalUjian;
use App\Models\Akademik\JadwalUjianRuang;
use App\Models\Akademik\KalenderPeristiwa;
use App\Models\Akademik\KelasKuliah;
use App\Models\Akademik\Krs;
use App\Models\Akademik\KrsDetail;
use App\Models\Akademik\Kurikulum;
use App\Models\Akademik\MataKuliah;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\Ruang;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\Staff;
use App\Services\Akademik\PemanfaatanRuangService;
use App\Support\Format;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Room utilisation (#42).
 *
 * The term is exactly two weeks, Monday 7 to Sunday 20 September 2026, and the
 * rooms open Monday–Friday 07:00–17:00: fifty available hours a week. Every
 * expected figure below can be worked out by hand from that frame, and the
 * comments do so — a test that only checks "some number came back" would pass
 * a report that counts an exam on top of a lecture twice.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    config([
        'ruang.operasional.hari' => [1, 2, 3, 4, 5],
        'ruang.operasional.jam_mulai' => '07:00',
        'ruang.operasional.jam_selesai' => '17:00',
        'ruang.ambang_jarang_persen' => 25,
        'ruang.langkah_peta_menit' => 60,
    ]);

    $this->term = TahunAkademik::factory()->term(2026, SemesterType::Ganjil)->aktif()->create([
        'tanggal_mulai' => '2026-09-07',
        'tanggal_selesai' => '2026-09-20',
    ]);
    $this->prodi = Prodi::factory()->create();
    $this->kurikulum = Kurikulum::factory()->create(['prodi_id' => $this->prodi->id]);
    $this->gedung = Gedung::create(['kode' => 'GA', 'nama' => 'Gedung A']);

    $this->service = app(PemanfaatanRuangService::class);
    $this->nim = 0;
});

function pruRuang(string $kode, int $kapasitas, array $ubah = []): Ruang
{
    return Ruang::create(array_merge([
        'gedung_id' => test()->gedung->id,
        'kode' => $kode,
        'nama' => 'Ruang '.$kode,
        'kapasitas' => $kapasitas,
        'jenis' => 'kelas',
        'is_active' => true,
    ], $ubah));
}

/** A class with this many students on an approved study plan. */
function pruKelas(int $peserta = 0, ?Prodi $prodi = null, string $kode = 'A'): KelasKuliah
{
    $prodi ??= test()->prodi;
    $mk = MataKuliah::factory()->create(['prodi_id' => $prodi->id, 'sks' => 2]);

    $kelas = KelasKuliah::factory()->create([
        'tahun_akademik_id' => test()->term->id,
        'mata_kuliah_id' => $mk->id,
        'prodi_id' => $prodi->id,
        'kode' => $kode,
        'sks' => 2,
        'kuota' => 60,
    ]);

    for ($i = 0; $i < $peserta; $i++) {
        pruIkutkan($kelas);
    }

    return $kelas->load('mataKuliah');
}

function pruIkutkan(KelasKuliah $kelas, KrsStatus $status = KrsStatus::Disetujui): void
{
    $mahasiswa = Mahasiswa::factory()->create([
        'nim' => sprintf('2642%06d', ++test()->nim),
        'prodi_id' => $kelas->prodi_id,
        'kurikulum_id' => test()->kurikulum->id,
        'status' => StudentStatus::Aktif,
    ]);

    $krs = Krs::create([
        'mahasiswa_id' => $mahasiswa->id,
        'tahun_akademik_id' => test()->term->id,
        'semester_ke' => 1,
        'status' => $status,
        'total_sks' => 2,
        'batas_sks' => 24,
    ]);

    KrsDetail::create(['krs_id' => $krs->id, 'kelas_kuliah_id' => $kelas->id, 'sks' => 2]);
}

function pruSlot(KelasKuliah $kelas, Ruang $ruang, int $hari, string $mulai, string $selesai): JadwalKuliah
{
    return JadwalKuliah::create([
        'kelas_kuliah_id' => $kelas->id,
        'ruang_id' => $ruang->id,
        'hari' => $hari,
        'jam_mulai' => $mulai,
        'jam_selesai' => $selesai,
    ]);
}

/** @param array<int, array{0: Ruang, 1: int}> $ruang room and seats, in seating order */
function pruUjian(KelasKuliah $kelas, string $tanggal, string $mulai, string $selesai, array $ruang, StatusJadwalUjian $status = StatusJadwalUjian::Terbit): JadwalUjian
{
    $ujian = JadwalUjian::create([
        'kelas_kuliah_id' => $kelas->id,
        'tahun_akademik_id' => $kelas->tahun_akademik_id,
        'jenis' => JenisUjianKelas::Uts,
        'tanggal' => $tanggal,
        'jam_mulai' => $mulai,
        'jam_selesai' => $selesai,
        'status' => $status,
    ]);

    foreach ($ruang as $i => [$r, $kursi]) {
        JadwalUjianRuang::create(['jadwal_ujian_id' => $ujian->id, 'ruang_id' => $r->id, 'kursi' => $kursi, 'urutan' => $i + 1]);
    }

    return $ujian;
}

function pruLibur(string $tanggal, array $ubah = []): KalenderPeristiwa
{
    return KalenderPeristiwa::create(array_merge([
        'judul' => 'Libur Uji',
        'jenis' => JenisPeristiwa::Libur,
        'tanggal_mulai' => $tanggal,
        'tanggal_selesai' => $tanggal,
        'sasaran' => SasaranPeristiwa::Semua,
        'status' => StatusPeristiwa::Terbit,
    ], $ubah));
}

function pruBaris(Ruang $ruang, array $filter = []): PemanfaatanRuang
{
    $laporan = test()->service->laporan(FilterPemanfaatanRuang::fromArray(test()->term, $filter));

    return $laporan->ruang->firstOrFail(fn (PemanfaatanRuang $r): bool => $r->ruangId === $ruang->id);
}

function pruStaf(string $peran): Staff
{
    $staf = Staff::factory()->create();
    $staf->assignRole($peran);

    return $staf;
}

/*
|--------------------------------------------------------------------------
| Hours used and seat occupancy
|--------------------------------------------------------------------------
*/

it('menghitung jam terpakai dari kuliah dan ujian terbit, irisan dihitung sekali', function () {
    $ruang = pruRuang('R1', 10);
    $kuliah = pruKelas(4);
    $diuji = pruKelas(6, kode: 'B');

    pruSlot($kuliah, $ruang, 1, '08:00', '10:00');

    // Rabu pekan pertama: ujian kelas lain, dua jam.
    pruUjian($diuji, '2026-09-09', '10:00', '12:00', [[$ruang, 10]]);

    // Senin pekan kedua: ujian 09–11 menumpuk kuliah 08–10 — terpakai 08–11.
    pruUjian($kuliah, '2026-09-14', '09:00', '11:00', [[$ruang, 10]]);

    // Draf dan peserta yang KRS-nya belum disetujui tidak dihitung.
    pruUjian($diuji, '2026-09-10', '08:00', '16:00', [[$ruang, 10]], StatusJadwalUjian::Draf);
    pruIkutkan($kuliah, KrsStatus::Diajukan);

    $baris = pruBaris($ruang);

    // 2 + 2 + 3 = 7 jam dalam dua pekan.
    expect($baris->jamTersediaPerMinggu)->toEqualWithDelta(50.0, 0.001)
        ->and($baris->jamTerpakaiPerMinggu)->toEqualWithDelta(3.5, 0.001)
        ->and($baris->jamKosongPerMinggu())->toEqualWithDelta(46.5, 0.001)
        ->and($baris->pemanfaatanPersen())->toEqualWithDelta(7.0, 0.001)
        ->and($baris->jamDiLuarPerMinggu)->toEqualWithDelta(0.0, 0.001)
        ->and($baris->slotKuliah)->toBe(1)
        ->and($baris->sesiUjian)->toBe(2);

    // Kursi terisi per menit: kuliah 4 × 240, ujian 6 × 120 dan 4 × 120,
    // dari 10 kursi × 480 menit — tumpukan tidak digabung untuk kursi.
    expect($baris->okupansiPersen)->toEqualWithDelta(2160 / 4800 * 100, 0.001);
});

it('mengisi kursi ujian berurutan per ruang dan melaporkan kelebihan peserta', function () {
    $sempit = pruRuang('R-SEMPIT', 3);
    $besar = pruRuang('R-BESAR', 10);
    $lab = pruRuang('LAB', 0);

    // Kuliah 4 peserta di ruang berkapasitas 3: kelebihan satu.
    pruSlot(pruKelas(4), $sempit, 2, '08:00', '10:00');

    // Ujian 7 peserta dibagi 5 + 5 kursi: 5 duduk di ruang sempit (kapasitas
    // 3), 2 di ruang besar. Hanya ruang pertama yang kelebihan.
    pruUjian(pruKelas(7, kode: 'B'), '2026-09-16', '13:00', '15:00', [[$sempit, 5], [$besar, 5]]);

    // Kapasitas yang belum diisi tidak pernah dilaporkan kelebihan.
    pruSlot(pruKelas(30, kode: 'C'), $lab, 3, '08:00', '10:00');

    $laporan = $this->service->laporan(FilterPemanfaatanRuang::fromArray($this->term, []));

    expect($laporan->kelebihan)->toHaveCount(2)
        ->and($laporan->kelebihan->first()->jenis)->toBe('uts')
        ->and($laporan->kelebihan->first()->peserta)->toBe(5)
        ->and($laporan->kelebihan->first()->selisih())->toBe(2)
        ->and($laporan->kelebihan->last()->jenis)->toBe('kuliah')
        ->and($laporan->kelebihan->last()->peserta)->toBe(4)
        ->and($laporan->kelebihan->pluck('ruangKode')->unique()->all())->toBe(['R-SEMPIT']);

    $baris = $laporan->ruang->keyBy('kode');

    expect($baris['R-SEMPIT']->kelebihan)->toBe(2)
        ->and($baris['R-BESAR']->kelebihan)->toBe(0)
        // 2 peserta × 120 menit dari 10 × 120.
        ->and($baris['R-BESAR']->okupansiPersen)->toEqualWithDelta(20.0, 0.001)
        ->and($baris['LAB']->okupansiPersen)->toBeNull();
});

it('melaporkan pemakaian di luar jam dan hari operasional secara terpisah', function () {
    $ruang = pruRuang('R1', 40);
    $kelas = pruKelas();

    pruSlot($kelas, $ruang, 1, '16:00', '18:00'); // satu jam di dalam, satu di luar
    pruSlot($kelas, $ruang, 6, '08:00', '10:00'); // Sabtu: tutup

    $baris = pruBaris($ruang);

    expect($baris->jamTerpakaiPerMinggu)->toEqualWithDelta(1.0, 0.001)
        ->and($baris->jamDiLuarPerMinggu)->toEqualWithDelta(3.0, 0.001)
        ->and($baris->pemanfaatanPersen())->toEqualWithDelta(2.0, 0.001);
});

it('tidak menganggap jam bersentuhan sebagai tumpang tindih', function () {
    $ruang = pruRuang('R1', 40);

    pruSlot(pruKelas(), $ruang, 1, '08:00:00', '10:00:00');
    pruSlot(pruKelas(kode: 'B'), $ruang, 1, '10:00', '12:00');

    expect(pruBaris($ruang)->jamTerpakaiPerMinggu)->toEqualWithDelta(4.0, 0.001);
});

/*
|--------------------------------------------------------------------------
| Holidays from the academic calendar
|--------------------------------------------------------------------------
*/

it('tidak menghitung libur kampus sebagai jam tersedia maupun terpakai', function () {
    $ruang = pruRuang('R1', 40);
    pruSlot(pruKelas(), $ruang, 1, '08:00', '10:00');

    pruLibur('2026-09-14');

    // Yang tidak berlaku: draf, khusus staf, dan akhir pekan (bukan hari
    // operasional, jadi tidak mengurangi apa pun).
    pruLibur('2026-09-15', ['status' => StatusPeristiwa::Draf]);
    pruLibur('2026-09-16', ['sasaran' => SasaranPeristiwa::Staf]);
    pruLibur('2026-09-19');

    $laporan = $this->service->laporan(FilterPemanfaatanRuang::fromArray($this->term, []));
    $baris = $laporan->ruang->first();

    // Sembilan hari operasional × 10 jam, dua pekan; kuliah hanya Senin pertama.
    expect($laporan->hariLibur)->toBe(1)
        ->and($baris->jamTersediaPerMinggu)->toEqualWithDelta(45.0, 0.001)
        ->and($baris->jamTerpakaiPerMinggu)->toEqualWithDelta(1.0, 0.001);

    // Peta panas Senin dihitung dari satu Senin yang tersedia saja.
    expect($baris->petaPanas[1][8 * 60])->toEqualWithDelta(1.0, 0.001)
        ->and($baris->petaPanas[1][10 * 60])->toEqualWithDelta(0.0, 0.001);
});

it('libur satu prodi hanya meniadakan kuliah prodi itu', function () {
    $ruang = pruRuang('R1', 40);
    $lain = Prodi::factory()->create();

    pruSlot(pruKelas(), $ruang, 2, '08:00', '10:00');
    pruSlot(pruKelas(prodi: $lain), $ruang, 2, '13:00', '15:00');

    pruLibur('2026-09-08', ['prodi_id' => $this->prodi->id]);

    $baris = pruBaris($ruang);

    // Tersedia utuh; kuliah prodi itu hilang satu Selasa: (2 + 2 + 2) / 2.
    expect($baris->jamTersediaPerMinggu)->toEqualWithDelta(50.0, 0.001)
        ->and($baris->jamTerpakaiPerMinggu)->toEqualWithDelta(3.0, 0.001);
});

/*
|--------------------------------------------------------------------------
| Heat map, ordering, filters
|--------------------------------------------------------------------------
*/

it('mengisi peta panas per hari dan jam', function () {
    $ruang = pruRuang('R1', 40);
    pruSlot(pruKelas(), $ruang, 1, '08:00', '09:30');

    $peta = pruBaris($ruang)->petaPanas;

    expect(array_keys($peta))->toBe([1, 2, 3, 4, 5])
        ->and(array_keys($peta[1]))->toBe(range(7 * 60, 16 * 60, 60))
        ->and($peta[1][8 * 60])->toEqualWithDelta(1.0, 0.001)
        ->and($peta[1][9 * 60])->toEqualWithDelta(0.5, 0.001)
        ->and($peta[2][8 * 60])->toEqualWithDelta(0.0, 0.001);
});

it('mengurutkan paling penuh atau paling kosong dan menandai ruang jarang dipakai', function () {
    $penuh = pruRuang('R-PENUH', 40);
    $sedang = pruRuang('R-SEDANG', 40);
    $kosong = pruRuang('R-KOSONG', 40);
    pruRuang('R-PENSIUN', 40, ['is_active' => false]);
    $pensiunTerpakai = pruRuang('R-LAMA', 40, ['is_active' => false]);

    foreach ([1, 2, 3, 4, 5] as $hari) {
        pruSlot(pruKelas(kode: 'P'.$hari), $penuh, $hari, '07:00', '13:00'); // 60 %
    }
    pruSlot(pruKelas(kode: 'S'), $sedang, 1, '07:00', '17:00'); // 20 %
    pruSlot(pruKelas(kode: 'L'), $pensiunTerpakai, 1, '07:00', '08:00');

    $urut = fn (string $urut) => $this->service
        ->laporan(FilterPemanfaatanRuang::fromArray($this->term, ['urut' => $urut]))
        ->ruang->pluck('kode')->all();

    // Ruang nonaktif yang tidak dipakai lenyap; yang masih dipakai tetap tampil.
    expect($urut('terpenuh'))->toBe(['R-PENUH', 'R-SEDANG', 'R-LAMA', 'R-KOSONG'])
        ->and($urut('terkosong'))->toBe(['R-KOSONG', 'R-LAMA', 'R-SEDANG', 'R-PENUH']);

    $laporan = $this->service->laporan(FilterPemanfaatanRuang::fromArray($this->term, []));

    // Di bawah 25 %: yang kosong dan yang 20 %. Ruang nonaktif tidak pernah
    // "jarang dipakai" — ia memang sedang tidak ditawarkan.
    expect($laporan->jarang()->pluck('kode')->all())->toBe(['R-KOSONG', 'R-SEDANG'])
        ->and($laporan->rerataPemanfaatan())->toEqualWithDelta((30 + 10 + 1) / 200 * 100, 0.001);
});

it('menyaring per gedung dan jenis ruang', function () {
    $lain = Gedung::create(['kode' => 'GB', 'nama' => 'Gedung B']);
    pruRuang('A-1', 40);
    pruRuang('LAB-1', 30, ['jenis' => 'laboratorium']);
    pruRuang('B-1', 40, ['gedung_id' => $lain->id]);

    $kode = fn (array $filter) => $this->service
        ->laporan(FilterPemanfaatanRuang::fromArray($this->term, $filter))
        ->ruang->pluck('kode')->all();

    expect($kode([]))->toBe(['A-1', 'LAB-1', 'B-1'])
        ->and($kode(['gedung_id' => $lain->id]))->toBe(['B-1'])
        ->and($kode(['jenis' => 'laboratorium']))->toBe(['LAB-1']);
});

it('menyusun laporan dengan jumlah kueri tetap berapa pun banyaknya ruang dan jadwal', function () {
    $hitung = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->service->laporan(FilterPemanfaatanRuang::fromArray($this->term, []));
        $jumlah = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $jumlah;
    };

    $r = pruRuang('R1', 40);
    $k = pruKelas(2);
    pruSlot($k, $r, 1, '08:00', '10:00');
    pruUjian($k, '2026-09-09', '08:00', '10:00', [[$r, 40]]);

    $sedikit = $hitung();

    foreach (range(2, 12) as $i) {
        $ruang = pruRuang('R'.$i, 40);
        $kelas = pruKelas(3, kode: 'K'.$i);
        pruSlot($kelas, $ruang, $i % 5 + 1, '10:00', '12:00');
        pruUjian($kelas, '2026-09-1'.($i % 5), '13:00', '15:00', [[$ruang, 20], [$r, 20]]);
    }
    pruLibur('2026-09-11');

    expect($hitung())->toBe($sedikit)->toBeLessThanOrEqual(6);
});

/*
|--------------------------------------------------------------------------
| Free rooms
|--------------------------------------------------------------------------
*/

it('mencari ruang kosong per hari dengan kapasitas minimum, terkecil lebih dulu', function () {
    $besar = pruRuang('R-60', 60);
    $sedang = pruRuang('R-40', 40);
    pruRuang('R-30', 30);
    pruRuang('R-20', 20);
    pruRuang('R-MATI', 50, ['is_active' => false]);

    pruSlot(pruKelas(), $sedang, 1, '08:00', '10:00');

    $cari = fn (string $mulai, string $selesai, int $hari = 1) => $this->service->cariKosong(KriteriaRuangKosong::fromArray($this->term, [
        'hari' => $hari, 'jam_mulai' => $mulai, 'jam_selesai' => $selesai, 'kapasitas_min' => 25,
    ]));

    $hasil = $cari('09:00', '11:00');

    expect($hasil->kosong->pluck('kode')->all())->toBe(['R-30', 'R-60'])
        ->and($hasil->terpakai)->toHaveCount(1)
        ->and($hasil->terpakai->first()['ruang']->is($sedang))->toBeTrue()
        ->and($hasil->terpakai->first()['oleh'][0])->toContain('Kelas A')
        ->and($hasil->diLuarOperasional)->toBeFalse()
        ->and($hasil->libur)->toBeNull();

    // Bersentuhan bukan bentrok; Sabtu di luar hari operasional.
    expect($cari('10:00', '12:00')->kosong->pluck('kode')->all())->toBe(['R-30', 'R-40', 'R-60'])
        ->and($cari('10:00', '12:00', 6)->diLuarOperasional)->toBeTrue();

    expect($besar->kapasitas)->toBe(60);
});

it('mencari ruang kosong pada tanggal dengan ujian terbit dan libur', function () {
    $a = pruRuang('R-A', 40);
    $b = pruRuang('R-B', 40);

    pruSlot(pruKelas(), $a, 3, '08:00', '10:00');
    pruUjian(pruKelas(kode: 'U'), '2026-09-09', '08:30', '09:30', [[$b, 40]]);
    pruUjian(pruKelas(kode: 'D'), '2026-09-16', '08:30', '09:30', [[$b, 40]], StatusJadwalUjian::Draf);

    $cari = fn (string $tanggal) => $this->service->cariKosong(KriteriaRuangKosong::fromArray($this->term, [
        'tanggal' => $tanggal, 'jam_mulai' => '09:00', 'jam_selesai' => '10:00',
    ]));

    // Rabu 9 Sep: kuliah mengisi R-A, ujian terbit mengisi R-B.
    $hasil = $cari('2026-09-09');
    expect($hasil->kriteria->hari)->toBe(3)
        ->and($hasil->kosong)->toBeEmpty()
        ->and($hasil->terpakai->firstWhere('ruang.kode', 'R-B')['oleh'][0])->toStartWith('UTS');

    // Rabu 16 Sep: hanya draf di R-B, jadi R-B kosong.
    expect($cari('2026-09-16')->kosong->pluck('kode')->all())->toBe(['R-B']);

    // Libur kampus: kuliah mingguan tidak berjalan.
    pruLibur('2026-09-16', ['judul' => 'Hari Raya Uji']);
    $libur = $cari('2026-09-16');

    expect($libur->libur)->toBe('Hari Raya Uji')
        ->and($libur->kosong->pluck('kode')->all())->toBe(['R-A', 'R-B']);

    // Di luar rentang semester, kuliah mingguan juga tidak ada.
    expect($cari('2026-09-23')->kosong->pluck('kode')->all())->toBe(['R-A', 'R-B']);
});

/*
|--------------------------------------------------------------------------
| Export, screens, policy
|--------------------------------------------------------------------------
*/

it('mengekspor laporan sebagai CSV satu baris per ruang', function () {
    $ruang = pruRuang('R1', 10);
    pruSlot(pruKelas(4), $ruang, 1, '08:00', '10:00');
    pruRuang('R2', 20);

    $respons = $this->actingAs(pruStaf('baak'), 'staff')
        ->get('/admin/pemanfaatan-ruang/ekspor?term='.$this->term->id)
        ->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
        ->assertDownload('pemanfaatan-ruang-'.$this->term->kode.'.csv');

    $baris = array_map(
        fn (string $b): array => str_getcsv($b, ',', '"', '\\'),
        array_values(array_filter(explode("\n", ltrim($respons->streamedContent(), "\xEF\xBB\xBF")))),
    );

    expect($baris)->toHaveCount(3)
        ->and($baris[0][0])->toBe('Gedung')
        ->and($baris[0][8])->toBe('Pemanfaatan (%)')
        ->and($baris[1])->toBe(['GA', 'R1', 'Ruang R1', 'Ruang Kelas', '10', '50', '2', '48', '4', '40', '0', '1', '0', '0', 'ya'])
        ->and($baris[2][1])->toBe('R2')
        ->and($baris[2][9])->toBe('');
});

it('membuka layar laporan dan pencarian untuk BAAK dan sarpras', function (string $peran) {
    $ruang = pruRuang('R1', 3);
    pruSlot(pruKelas(4), $ruang, 1, '08:00', '10:00');
    pruLibur('2026-09-14');

    $staf = pruStaf($peran);

    $this->actingAs($staf, 'staff')
        ->get('/admin/pemanfaatan-ruang?term='.$this->term->id.'&ruang='.$ruang->uuid.'&urut=terpenuh')
        ->assertOk()
        ->assertSee('R1')
        // Dua jam dalam dua pekan, dari 45 jam tersedia sepekan sesudah libur.
        ->assertSee(Format::angka(1 / 45 * 100, 1).'%')
        ->assertSee(__('pemanfaatan_ruang.kartu.daftar_kelebihan'))
        ->assertSee('Senin–Jumat');

    $this->actingAs($staf, 'staff')
        ->get('/admin/pemanfaatan-ruang/ruang-kosong?term='.$this->term->id)
        ->assertOk()
        ->assertSee(__('pemanfaatan_ruang.kosong.mulai'));

    $this->actingAs($staf, 'staff')
        ->get('/admin/pemanfaatan-ruang/ruang-kosong?term='.$this->term->id.'&hari=1&jam_mulai=10:00&jam_selesai=12:00&kapasitas_min=2')
        ->assertOk()
        ->assertSee(__('pemanfaatan_ruang.kosong.petunjuk'))
        ->assertSee('R1');
})->with(['baak', 'sarpras', 'super-admin']);

it('menolak pencarian dengan jam selesai sebelum jam mulai', function () {
    $this->actingAs(pruStaf('baak'), 'staff')
        ->from('/admin/pemanfaatan-ruang/ruang-kosong')
        ->get('/admin/pemanfaatan-ruang/ruang-kosong?hari=1&jam_mulai=12:00&jam_selesai=10:00')
        ->assertRedirect('/admin/pemanfaatan-ruang/ruang-kosong')
        ->assertSessionHasErrors('jam_selesai');
});

it('hanya membuka laporan bagi staf berizin ruang.view', function () {
    $keuangan = pruStaf('keuangan');

    $this->actingAs($keuangan, 'staff')->get('/admin/pemanfaatan-ruang')->assertForbidden();
    $this->actingAs($keuangan, 'staff')->get('/admin/pemanfaatan-ruang/ekspor')->assertForbidden();
    $this->actingAs($keuangan, 'staff')->get('/admin/pemanfaatan-ruang/ruang-kosong')->assertForbidden();

    $dosen = Dosen::factory()->create();
    $dosen->assignRole('dosen');
    $mahasiswa = Mahasiswa::factory()->create(['prodi_id' => $this->prodi->id, 'kurikulum_id' => $this->kurikulum->id]);
    $mahasiswa->assignRole('mahasiswa');

    expect(Gate::forUser(pruStaf('baak'))->allows('viewUtilization', Ruang::class))->toBeTrue()
        ->and(Gate::forUser(pruStaf('sarpras'))->allows('viewUtilization', Ruang::class))->toBeTrue()
        ->and(Gate::forUser(pruStaf('pimpinan'))->allows('viewUtilization', Ruang::class))->toBeTrue()
        ->and(Gate::forUser($keuangan)->allows('viewUtilization', Ruang::class))->toBeFalse()
        ->and(Gate::forUser($dosen)->allows('viewUtilization', Ruang::class))->toBeFalse()
        ->and(Gate::forUser($mahasiswa)->allows('viewUtilization', Ruang::class))->toBeFalse();
});

it('tidak membuka laporan dari portal dosen', function () {
    $dosen = Dosen::factory()->create();
    $dosen->assignRole('dosen');

    // Guard staf menolak lebih dulu; policy bahkan tidak ditanya.
    $this->actingAs($dosen, 'dosen')->get('/admin/pemanfaatan-ruang')->assertRedirect();
});

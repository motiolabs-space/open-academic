<?php

declare(strict_types=1);

use App\DTOs\Akademik\BentrokJadwal;
use App\DTOs\Akademik\FilterKalender;
use App\DTOs\Akademik\JadwalUjianData;
use App\DTOs\Akademik\PenyusunanUjianData;
use App\DTOs\Akademik\PeristiwaKalender;
use App\DTOs\Akademik\PeristiwaKalenderData;
use App\Enums\JenisPeristiwa;
use App\Enums\JenisUjianKelas;
use App\Enums\KrsStatus;
use App\Enums\SasaranPeristiwa;
use App\Enums\SemesterType;
use App\Enums\StatusJadwalUjian;
use App\Enums\StatusPeristiwa;
use App\Enums\StudentStatus;
use App\Exceptions\AturanAkademikException;
use App\Models\Akademik\Gedung;
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
use App\Models\Edom\EdomPeriode;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Kemahasiswaan\WisudaPeriode;
use App\Models\Kemahasiswaan\Yudisium;
use App\Models\Pmb\PmbGelombang;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\Staff;
use App\Models\System\CalendarFeedToken;
use App\Models\System\LogAktivitas;
use App\Services\Akademik\JadwalUjianService;
use App\Services\Akademik\KalenderService;
use App\Services\Akademik\LanggananKalenderService;
use App\Support\Format;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * The academic event calendar (#35).
 *
 * The aggregation tests pin dates and counts read back from each source, not
 * merely that "some" entry came out: SQLite treats an unknown quoted column as
 * a string literal, so a typo in a grouped query returns a plausible-looking
 * wrong row with every test that checks the status code still green.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->travelTo(Carbon::parse('2026-10-15 09:00'));

    // Kuliah 1 Sep 2026 – 28 Feb 2027; KRS 11–25 Agu, revisi s.d. 15 Sep.
    $this->term = TahunAkademik::factory()->term(2026, SemesterType::Ganjil)->aktif()->create();
    $this->prodiA = Prodi::factory()->create(['nama' => 'Informatika Uji']);
    $this->prodiB = Prodi::factory()->create(['nama' => 'Akuntansi Uji']);
    $this->gedung = Gedung::create(['kode' => 'GK', 'nama' => 'Gedung Kalender']);

    $this->kalender = app(KalenderService::class);
});

function kalStaf(string $peran = 'baak'): Staff
{
    $staf = Staff::factory()->create();
    $staf->assignRole($peran);

    return $staf;
}

function kalDosen(): Dosen
{
    $dosen = Dosen::factory()->create();
    $dosen->assignRole('dosen');

    return $dosen;
}

function kalMahasiswa(Prodi $prodi, string $nim = '2611000001'): Mahasiswa
{
    $kurikulum = Kurikulum::query()->where('prodi_id', $prodi->id)->first()
        ?? Kurikulum::factory()->create(['prodi_id' => $prodi->id]);

    $m = Mahasiswa::factory()->create([
        'nim' => $nim,
        'prodi_id' => $prodi->id,
        'kurikulum_id' => $kurikulum->id,
        'status' => StudentStatus::Aktif,
    ]);
    $m->assignRole('mahasiswa');

    return $m;
}

function kalKelas(Prodi $prodi, ?Dosen $dosen = null, string $nama = 'Basis Data'): KelasKuliah
{
    $mk = MataKuliah::factory()->create(['prodi_id' => $prodi->id, 'nama' => $nama, 'sks' => 3]);

    $kelas = KelasKuliah::factory()->create([
        'tahun_akademik_id' => test()->term->id,
        'mata_kuliah_id' => $mk->id,
        'prodi_id' => $prodi->id,
        'kode' => 'A',
        'sks' => 3,
        'kuota' => 40,
    ]);

    if ($dosen !== null) {
        $kelas->dosen()->attach($dosen->id, ['peran' => 'pengampu', 'porsi_sks' => 3]);
    }

    return $kelas->load('mataKuliah');
}

function kalIkutkan(Mahasiswa $m, KelasKuliah $kelas): void
{
    $krs = Krs::firstOrCreate(
        ['mahasiswa_id' => $m->id, 'tahun_akademik_id' => test()->term->id],
        ['semester_ke' => 1, 'status' => KrsStatus::Disetujui, 'total_sks' => 0, 'batas_sks' => 24],
    );

    KrsDetail::create(['krs_id' => $krs->id, 'kelas_kuliah_id' => $kelas->id, 'sks' => 3]);
}

function kalUjian(KelasKuliah $kelas, string $tanggal, StatusJadwalUjian $status = StatusJadwalUjian::Terbit, ?Ruang $ruang = null): JadwalUjian
{
    $j = JadwalUjian::create([
        'kelas_kuliah_id' => $kelas->id,
        'tahun_akademik_id' => $kelas->tahun_akademik_id,
        'jenis' => JenisUjianKelas::Uts,
        'tanggal' => $tanggal,
        'jam_mulai' => '08:00',
        'jam_selesai' => '10:00',
        'status' => $status,
    ]);

    if ($ruang !== null) {
        JadwalUjianRuang::create(['jadwal_ujian_id' => $j->id, 'ruang_id' => $ruang->id, 'kursi' => 40]);
    }

    return $j;
}

function kalRuang(string $kode = 'R101', int $kapasitas = 40): Ruang
{
    return Ruang::create([
        'gedung_id' => test()->gedung->id,
        'kode' => $kode,
        'nama' => 'Ruang '.$kode,
        'kapasitas' => $kapasitas,
        'is_active' => true,
    ]);
}

/** @param array<string, mixed> $ubah */
function kalPeristiwa(array $ubah = []): KalenderPeristiwa
{
    return KalenderPeristiwa::create(array_merge([
        'judul' => 'Libur Uji',
        'jenis' => JenisPeristiwa::Libur,
        'tanggal_mulai' => '2026-10-19',
        'tanggal_selesai' => '2026-10-19',
        'sasaran' => SasaranPeristiwa::Semua,
        'status' => StatusPeristiwa::Terbit,
    ], $ubah));
}

/**
 * @param Collection<int, PeristiwaKalender> $peristiwa
 * @return list<string>
 */
function kalJudul(Collection $peristiwa): array
{
    return $peristiwa->pluck('judul')->sort()->values()->all();
}

/** @param Collection<int, PeristiwaKalender> $peristiwa */
function kalSatu(Collection $peristiwa, JenisPeristiwa $jenis): PeristiwaKalender
{
    $cocok = $peristiwa->where('jenis', $jenis)->values();

    expect($cocok)->toHaveCount(1);

    return $cocok->first();
}

describe('lapisan baca', function () {
    it('membaca KRS, perubahan KRS, nilai, dan perkuliahan dari baris tahun akademik', function () {
        $agustus = $this->kalender->semua(new FilterKalender('2026-08-01', '2026-09-30'));

        $krs = kalSatu($agustus, JenisPeristiwa::Krs);
        expect($krs->mulai->toDateString())->toBe('2026-08-11')
            ->and($krs->selesai->toDateString())->toBe('2026-08-25')
            ->and($krs->sasaran)->toBe(SasaranPeristiwa::Mahasiswa)
            ->and($krs->manual())->toBeFalse();

        // Perubahan dibuka sehari sesudah pengisian ditutup, seperti krsDibuka().
        $perubahan = kalSatu($agustus, JenisPeristiwa::PerubahanKrs);
        expect($perubahan->mulai->toDateString())->toBe('2026-08-26')
            ->and($perubahan->selesai->toDateString())->toBe('2026-09-15');

        expect($agustus->where('jenis', JenisPeristiwa::Perkuliahan)->pluck('mulai')->map->toDateString()->all())
            ->toBe(['2026-09-01']);

        $nilai = kalSatu($this->kalender->semua(new FilterKalender('2027-02-01', '2027-03-31')), JenisPeristiwa::Nilai);
        expect($nilai->mulai->toDateString())->toBe('2027-02-07')
            ->and($nilai->selesai->toDateString())->toBe('2027-03-14')
            ->and($nilai->sasaran)->toBe(SasaranPeristiwa::Dosen);
    });

    it('mengikuti perubahan di sumber tanpa menyalin apa pun', function () {
        $this->term->update(['krs_selesai' => '2026-08-20']);

        $krs = kalSatu($this->kalender->semua(new FilterKalender('2026-08-01', '2026-08-31')), JenisPeristiwa::Krs);

        expect($krs->selesai->toDateString())->toBe('2026-08-20')
            ->and(KalenderPeristiwa::count())->toBe(0);
    });

    it('membaca masa UTS dari sesi terbit saja, per prodi, utuh walau melewati batas bulan', function () {
        kalUjian(kalKelas($this->prodiA, nama: 'Algoritma'), '2026-10-27');
        kalUjian(kalKelas($this->prodiA, nama: 'Jaringan'), '2026-11-03');
        kalUjian(kalKelas($this->prodiA, nama: 'Statistika'), '2026-11-06', StatusJadwalUjian::Draf);
        kalUjian(kalKelas($this->prodiB, nama: 'Pajak'), '2026-10-28');

        $oktober = $this->kalender->semua(new FilterKalender('2026-10-01', '2026-10-31', jenis: JenisPeristiwa::Ujian));

        $a = $oktober->firstWhere('prodiId', $this->prodiA->id);
        expect($oktober)->toHaveCount(2)
            ->and($a->mulai->toDateString())->toBe('2026-10-27')
            ->and($a->selesai->toDateString())->toBe('2026-11-03')
            ->and($a->prodiNama)->toBe('Informatika Uji')
            ->and($a->keterangan)->toBe('2 sesi ujian terbit')
            ->and($a->judul)->toBe('Masa UTS '.$this->term->nama);

        // Sesi terakhir tepat di hari terakhir rentang tetap terbaca.
        expect($this->kalender->semua(new FilterKalender('2026-10-01', '2026-10-27', jenis: JenisPeristiwa::Ujian)))->toHaveCount(1);
    });

    it('membaca wisuda, yudisium sebagai jumlah, EDOM yang aktif, dan gelombang PMB', function () {
        WisudaPeriode::create(['nama' => 'Wisuda Periode I', 'tanggal' => '2026-10-24', 'lokasi' => 'Auditorium']);

        foreach (['2611000101', '2611000102'] as $nim) {
            $m = kalMahasiswa($this->prodiA, $nim);
            Yudisium::create([
                'mahasiswa_id' => $m->id, 'tahun_akademik_id' => $this->term->id, 'total_sks' => 144, 'ipk' => 3.5,
                'status' => 'ditetapkan', 'tanggal_yudisium' => Carbon::parse('2026-10-08 10:15'),
            ]);
        }
        $diajukan = kalMahasiswa($this->prodiA, '2611000103');
        Yudisium::create([
            'mahasiswa_id' => $diajukan->id, 'tahun_akademik_id' => $this->term->id, 'total_sks' => 144, 'ipk' => 3.1,
            'status' => 'diajukan', 'tanggal_yudisium' => '2026-10-08',
        ]);

        EdomPeriode::create(['tahun_akademik_id' => $this->term->id, 'nama' => 'EDOM Ganjil', 'mulai' => '2026-10-12', 'selesai' => '2026-10-30', 'is_active' => true]);
        // Satu periode per semester; yang dimatikan diambil dari semester lain.
        $lain = TahunAkademik::factory()->term(2025, SemesterType::Genap)->create();
        EdomPeriode::create(['tahun_akademik_id' => $lain->id, 'nama' => 'EDOM Mati', 'mulai' => '2026-10-12', 'selesai' => '2026-10-30', 'is_active' => false]);

        PmbGelombang::create([
            'tahun_akademik_id' => $this->term->id, 'kode' => 'PMB-K1', 'nama' => 'Gelombang I', 'jalur' => 'reguler',
            'tanggal_mulai' => '2026-10-01', 'tanggal_selesai' => '2026-12-31', 'biaya_pendaftaran' => 0, 'is_active' => true,
        ]);

        $oktober = $this->kalender->semua(new FilterKalender('2026-10-01', '2026-10-31'));

        $wisuda = kalSatu($oktober, JenisPeristiwa::Wisuda);
        expect($wisuda->judul)->toBe('Wisuda: Wisuda Periode I')
            ->and($wisuda->lokasi)->toBe('Auditorium')
            ->and($wisuda->mulai->toDateString())->toBe('2026-10-24');

        $yudisium = kalSatu($oktober, JenisPeristiwa::Yudisium);
        expect($yudisium->mulai->toDateString())->toBe('2026-10-08')
            ->and($yudisium->keterangan)->toBe('2 lulusan ditetapkan')
            ->and($yudisium->prodiId)->toBe($this->prodiA->id)
            ->and($yudisium->judul.$yudisium->keterangan)->not->toContain('2611000101');

        expect(kalSatu($oktober, JenisPeristiwa::Edom)->judul)->toBe('Evaluasi dosen: EDOM Ganjil');

        $pmb = kalSatu($oktober, JenisPeristiwa::Pmb);
        expect($pmb->sasaran)->toBe(SasaranPeristiwa::Staf)
            ->and($pmb->selesai->toDateString())->toBe('2026-12-31');
    });

    it('menyusun grid bulan Senin–Minggu dengan peristiwa per hari', function () {
        kalPeristiwa(['tanggal_mulai' => '2026-10-30', 'tanggal_selesai' => '2026-11-02']);

        $bulan = Carbon::parse('2026-10-01');
        $grid = $this->kalender->grid($bulan, $this->kalender->semua(FilterKalender::gridBulan($bulan)));

        // Oktober 2026 dimulai Kamis: grid berawal Senin 28 Sep, lima pekan.
        expect($grid)->toHaveCount(5)
            ->and($grid[0][0]['tanggal']->toDateString())->toBe('2026-09-28')
            ->and($grid[0][0]['diBulan'])->toBeFalse()
            ->and($grid[4][6]['tanggal']->toDateString())->toBe('2026-11-01');

        $hari = collect($grid)->flatten(1)->keyBy(fn (array $h): string => $h['tanggal']->toDateString());
        expect($hari['2026-10-30']['libur'])->toBeTrue()
            ->and($hari['2026-11-01']['libur'])->toBeTrue()
            ->and($hari['2026-10-29']['libur'])->toBeFalse()
            ->and($hari['2026-10-15']['hariIni'])->toBeTrue();
    });
});

describe('peristiwa manual', function () {
    it('disimpan, diubah, dan dihapus lunak oleh BAAK dengan jejak audit', function () {
        $staf = kalStaf();

        $this->actingAs($staf, 'staff')->get('/admin/kalender/peristiwa/baru')->assertOk();

        $this->actingAs($staf, 'staff')->post('/admin/kalender/peristiwa', [
            'judul' => 'Dies Natalis',
            'jenis' => 'kegiatan',
            'tanggal_mulai' => '2026-11-11',
            'tanggal_selesai' => '',
            'sasaran' => 'semua',
            'prodi_id' => '',
            'status' => 'terbit',
            'keterangan' => 'Upacara pagi.',
        ])->assertRedirect('/admin/kalender?bulan=2026-11')->assertSessionHas('sukses');

        $p = KalenderPeristiwa::sole();
        expect($p->tanggal_selesai->toDateString())->toBe('2026-11-11')
            ->and($p->jenis)->toBe(JenisPeristiwa::Kegiatan)
            ->and(LogAktivitas::where('subject_type', $p->getMorphClass())->where('subject_id', $p->id)->where('event', 'created')->exists())->toBeTrue();

        $this->actingAs($staf, 'staff')->get('/admin/kalender/peristiwa/'.$p->uuid.'/ubah')->assertOk()->assertSee('Dies Natalis');
        $this->actingAs($staf, 'staff')->get('/admin/kalender/peristiwa?tahun=2026')->assertOk()->assertSee('Dies Natalis');

        $this->actingAs($staf, 'staff')->put('/admin/kalender/peristiwa/'.$p->uuid, [
            'judul' => 'Dies Natalis ke-25',
            'jenis' => 'kegiatan',
            'tanggal_mulai' => '2026-11-11',
            'tanggal_selesai' => '2026-11-12',
            'sasaran' => 'semua',
            'status' => 'terbit',
        ])->assertRedirect();

        expect($p->refresh()->judul)->toBe('Dies Natalis ke-25')
            ->and($p->tanggal_selesai->toDateString())->toBe('2026-11-12')
            ->and(LogAktivitas::where('subject_id', $p->id)->where('subject_type', $p->getMorphClass())->where('event', 'updated')->exists())->toBeTrue();

        $this->actingAs($staf, 'staff')->delete('/admin/kalender/peristiwa/'.$p->uuid)->assertRedirect();

        expect(KalenderPeristiwa::count())->toBe(0)
            ->and(KalenderPeristiwa::withTrashed()->count())->toBe(1)
            ->and(LogAktivitas::where('subject_id', $p->id)->where('subject_type', $p->getMorphClass())->where('event', 'deleted')->exists())->toBeTrue();
    });

    it('menolak rentang terbalik dan jenis yang dimiliki modul lain', function () {
        $staf = kalStaf();
        $dasar = ['judul' => 'X', 'jenis' => 'libur', 'tanggal_mulai' => '2026-11-11', 'sasaran' => 'semua', 'status' => 'draf'];

        $this->actingAs($staf, 'staff')->post('/admin/kalender/peristiwa', [...$dasar, 'tanggal_selesai' => '2026-11-10'])
            ->assertSessionHasErrors('tanggal_selesai');

        $this->actingAs($staf, 'staff')->post('/admin/kalender/peristiwa', [...$dasar, 'jenis' => 'krs'])
            ->assertSessionHasErrors('jenis');

        expect(KalenderPeristiwa::count())->toBe(0);

        // Service menolak hal yang sama bagi pemanggil selain layar.
        expect(fn () => $this->kalender->simpan(PeristiwaKalenderData::fromArray([...$dasar, 'jenis' => 'ujian'])))
            ->toThrow(AturanAkademikException::class);
    });
});

describe('filter per portal', function () {
    beforeEach(function () {
        kalPeristiwa(['judul' => 'Untuk Semua']);
        kalPeristiwa(['judul' => 'Mahasiswa Prodi A', 'jenis' => JenisPeristiwa::Kegiatan, 'sasaran' => SasaranPeristiwa::Mahasiswa, 'prodi_id' => $this->prodiA->id]);
        kalPeristiwa(['judul' => 'Mahasiswa Prodi B', 'jenis' => JenisPeristiwa::Kegiatan, 'sasaran' => SasaranPeristiwa::Mahasiswa, 'prodi_id' => $this->prodiB->id]);
        kalPeristiwa(['judul' => 'Khusus Dosen', 'jenis' => JenisPeristiwa::Batas, 'sasaran' => SasaranPeristiwa::Dosen]);
        kalPeristiwa(['judul' => 'Khusus Staf', 'jenis' => JenisPeristiwa::Kegiatan, 'sasaran' => SasaranPeristiwa::Staf]);
        kalPeristiwa(['judul' => 'Masih Draf', 'status' => StatusPeristiwa::Draf]);

        $this->oktober = new FilterKalender('2026-10-01', '2026-10-31', jenis: null);
        $this->manual = fn (Collection $c): array => kalJudul($c->filter->manual());
    });

    it('mahasiswa hanya melihat yang terbit untuk mahasiswa atau semua, di prodinya sendiri', function () {
        $m = kalMahasiswa($this->prodiA);

        // Filter prodi lain dari URL tidak berpengaruh: prodinya dipaku.
        expect(($this->manual)($this->kalender->untuk($m, $this->oktober->denganProdi($this->prodiB->id), termasukDraf: true)))
            ->toBe(['Mahasiswa Prodi A', 'Untuk Semua']);

        $this->actingAs($m, 'mahasiswa')->get('/mahasiswa/kalender?bulan=2026-10&prodi='.$this->prodiB->id.'&sasaran=staf')
            ->assertOk()
            ->assertSee('Mahasiswa Prodi A')
            ->assertDontSee('Mahasiswa Prodi B')
            ->assertDontSee('Khusus Dosen')
            ->assertDontSee('Khusus Staf')
            ->assertDontSee('Masih Draf');
    });

    it('dosen melihat yang untuk dosen, mahasiswa, dan semua — tidak yang khusus staf', function () {
        $d = kalDosen();

        expect(($this->manual)($this->kalender->untuk($d, $this->oktober)))
            ->toBe(['Khusus Dosen', 'Mahasiswa Prodi A', 'Mahasiswa Prodi B', 'Untuk Semua']);

        expect(($this->manual)($this->kalender->untuk($d, new FilterKalender('2026-10-01', '2026-10-31', SasaranPeristiwa::Dosen))))
            ->toBe(['Khusus Dosen', 'Untuk Semua']);

        $this->actingAs($d, 'dosen')->get('/dosen/kalender?bulan=2026-10&tampilan=daftar&prodi='.$this->prodiB->id)
            ->assertOk()
            ->assertSee('Mahasiswa Prodi B')
            ->assertDontSee('Mahasiswa Prodi A')
            ->assertDontSee('Khusus Staf');
    });

    it('staf menyaring sasaran dan prodi; draf hanya terlihat oleh pengelola', function () {
        $baak = kalStaf();
        $keuangan = kalStaf('keuangan');

        expect(($this->manual)($this->kalender->untuk($baak, new FilterKalender('2026-10-01', '2026-10-31', SasaranPeristiwa::Staf))))
            ->toBe(['Khusus Staf', 'Untuk Semua']);

        expect(($this->manual)($this->kalender->untuk($baak, new FilterKalender('2026-10-01', '2026-10-31', SasaranPeristiwa::Mahasiswa, $this->prodiB->id))))
            ->toBe(['Mahasiswa Prodi B', 'Untuk Semua']);

        $this->actingAs($baak, 'staff')->get('/admin/kalender?bulan=2026-10')->assertOk()->assertSee('Masih Draf')->assertSee('Khusus Staf');
        $this->actingAs($keuangan, 'staff')->get('/admin/kalender?bulan=2026-10')->assertOk()->assertDontSee('Masih Draf')->assertSee('Khusus Staf');
    });

    it('menambahkan sesi ujian milik mahasiswa dan dosen sendiri, berjam dan berruang', function () {
        $dosen = kalDosen();
        $kelas = kalKelas($this->prodiA, $dosen, 'Sistem Operasi');
        $m = kalMahasiswa($this->prodiA);
        $lain = kalMahasiswa($this->prodiA, '2611000002');
        kalIkutkan($m, $kelas);
        kalUjian($kelas, '2026-10-27', ruang: kalRuang('R204'));

        $sesi = $this->kalender->untuk($m, $this->oktober)->filter->pribadi->values();
        expect($sesi)->toHaveCount(1)
            ->and($sesi[0]->judul)->toBe('UTS · Sistem Operasi')
            ->and($sesi[0]->jamMulai)->toBe('08:00')
            ->and($sesi[0]->lokasi)->toBe('R204');

        expect($this->kalender->untuk($lain, $this->oktober)->filter->pribadi)->toBeEmpty()
            ->and($this->kalender->untuk($dosen, $this->oktober)->filter->pribadi)->toHaveCount(1);

        $this->actingAs($m, 'mahasiswa')->get('/mahasiswa/kalender?bulan=2026-10')->assertOk()->assertSee('UTS · Sistem Operasi');
    });
});

describe('policy', function () {
    it('hanya staf berizin kalender.manage yang menulis di kalender', function () {
        $m = kalMahasiswa($this->prodiA);

        expect(Gate::forUser(kalStaf())->allows('create', KalenderPeristiwa::class))->toBeTrue()
            ->and(Gate::forUser(kalStaf('keuangan'))->allows('create', KalenderPeristiwa::class))->toBeFalse()
            ->and(Gate::forUser(kalDosen())->allows('create', KalenderPeristiwa::class))->toBeFalse()
            ->and(Gate::forUser($m)->allows('create', KalenderPeristiwa::class))->toBeFalse()
            ->and(Gate::forUser($m)->allows('viewAny', KalenderPeristiwa::class))->toBeTrue();

        $keuangan = kalStaf('keuangan');
        $p = kalPeristiwa();

        $this->actingAs($keuangan, 'staff')->get('/admin/kalender/peristiwa')->assertForbidden();
        $this->actingAs($keuangan, 'staff')->post('/admin/kalender/peristiwa', [
            'judul' => 'X', 'jenis' => 'libur', 'tanggal_mulai' => '2026-11-11', 'sasaran' => 'semua', 'status' => 'terbit',
        ])->assertForbidden();
        $this->actingAs($keuangan, 'staff')->delete('/admin/kalender/peristiwa/'.$p->uuid)->assertForbidden();

        expect(KalenderPeristiwa::count())->toBe(1);
    });

    it('membatasi satu peristiwa menurut status, sasaran, dan prodi pembacanya', function () {
        $mA = kalMahasiswa($this->prodiA);
        $dosen = kalDosen();

        $prodiB = kalPeristiwa(['prodi_id' => $this->prodiB->id]);
        $draf = kalPeristiwa(['status' => StatusPeristiwa::Draf]);
        $staf = kalPeristiwa(['sasaran' => SasaranPeristiwa::Staf]);
        $semua = kalPeristiwa();

        expect(Gate::forUser($mA)->allows('view', $semua))->toBeTrue()
            ->and(Gate::forUser($mA)->allows('view', $prodiB))->toBeFalse()
            ->and(Gate::forUser($dosen)->allows('view', $prodiB))->toBeTrue()
            ->and(Gate::forUser($dosen)->allows('view', $draf))->toBeFalse()
            ->and(Gate::forUser($dosen)->allows('view', $staf))->toBeFalse()
            ->and(Gate::forUser(kalStaf())->allows('view', $draf))->toBeTrue()
            ->and(Gate::forUser(kalStaf('keuangan'))->allows('view', $draf))->toBeFalse();
    });
});

describe('ekspor iCalendar', function () {
    it('menerbitkan tautan sekali tampil, menyimpan hash-nya saja, dan menyajikan feed tanpa data pribadi', function () {
        kalPeristiwa(['judul' => 'Libur Nasional, Hari Raya; Uji']);
        kalPeristiwa(['judul' => 'Masih Draf', 'status' => StatusPeristiwa::Draf]);
        $kelas = kalKelas($this->prodiA, nama: 'Kecerdasan Buatan');
        $m = kalMahasiswa($this->prodiA, '2611009999');
        kalIkutkan($m, $kelas);
        kalUjian($kelas, '2026-10-27', ruang: kalRuang('R301'));

        $tanggapan = $this->actingAs($m, 'mahasiswa')->post('/mahasiswa/kalender/langganan')->assertRedirect('/mahasiswa/kalender');
        $url = session('tautan_kalender');
        $token = basename((string) parse_url($url, PHP_URL_PATH), '.ics');

        expect($url)->toStartWith(url('/kalender/'))
            ->and(strlen($token))->toBe(48)
            ->and(CalendarFeedToken::sole()->token_hash)->toBe(hash('sha256', $token))
            ->and(CalendarFeedToken::query()->where('token_hash', $token)->exists())->toBeFalse();

        // Tampil pada halaman tujuan pengalihan, lalu tidak pernah lagi.
        $this->actingAs($m, 'mahasiswa')->get('/mahasiswa/kalender')->assertOk()->assertSee($token);
        $this->actingAs($m, 'mahasiswa')->get('/mahasiswa/kalender')->assertOk()->assertDontSee($token);

        auth('mahasiswa')->logout();

        $ics = $this->get('/kalender/'.$token.'.ics')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/calendar; charset=utf-8')
            ->getContent();

        $isi = str_replace("\r\n ", '', $ics);

        expect($ics)->toStartWith("BEGIN:VCALENDAR\r\n")
            ->and($ics)->toEndWith("END:VCALENDAR\r\n")
            ->and($isi)->toContain('SUMMARY:Libur Nasional\, Hari Raya\; Uji')
            ->and($isi)->toContain('DTSTART;VALUE=DATE:20261019')
            ->and($isi)->toContain('DTEND;VALUE=DATE:20261020')
            ->and($isi)->toContain('SUMMARY:UTS · Kecerdasan Buatan')
            // 08.00 WIB = 01.00 UTC.
            ->and($isi)->toContain('DTSTART:20261027T010000Z')
            ->and($isi)->toContain('LOCATION:R301')
            ->and($isi)->toContain('SUMMARY:Pengisian KRS')
            ->and($isi)->not->toContain('Masih Draf')
            ->and($isi)->not->toContain('2611009999')
            ->and($isi)->not->toContain($m->nama)
            ->and($isi)->not->toContain((string) $m->email);

        foreach (explode("\r\n", $ics) as $baris) {
            expect(strlen($baris))->toBeLessThanOrEqual(75);
        }

        expect(CalendarFeedToken::sole()->last_used_at)->not->toBeNull();
    });

    it('mencabut tautan, dan membuat ulang mematikan tautan lama', function () {
        $d = kalDosen();
        $layanan = app(LanggananKalenderService::class);

        $lama = $layanan->terbitkan($d);
        $this->get('/kalender/'.$lama.'.ics')->assertOk();

        $this->actingAs($d, 'dosen')->post('/dosen/kalender/langganan')->assertRedirect('/dosen/kalender');
        $baru = basename((string) parse_url(session('tautan_kalender'), PHP_URL_PATH), '.ics');

        auth('dosen')->logout();
        $this->get('/kalender/'.$lama.'.ics')->assertNotFound();
        $this->get('/kalender/'.$baru.'.ics')->assertOk();

        $this->actingAs($d, 'dosen')->delete('/dosen/kalender/langganan')->assertRedirect('/dosen/kalender');
        auth('dosen')->logout();

        $this->get('/kalender/'.$baru.'.ics')->assertNotFound();
        $this->get('/kalender/'.str_repeat('a', 48).'.ics')->assertNotFound();

        expect(CalendarFeedToken::count())->toBe(2)
            ->and(CalendarFeedToken::aktif()->count())->toBe(0);
    });

    it('berhenti melayani feed akun yang dinonaktifkan', function () {
        $staf = kalStaf();
        $token = app(LanggananKalenderService::class)->terbitkan($staf);

        $this->get('/kalender/'.$token.'.ics')->assertOk();

        $staf->update(['is_active' => false]);

        $this->get('/kalender/'.$token.'.ics')->assertNotFound();
    });

    it('melipat baris panjang tanpa memotong karakter multibita', function () {
        $judul = str_repeat('Pekan Ujian – ', 12);
        $ics = app(LanggananKalenderService::class)->render(collect([
            new PeristiwaKalender('uji-lipat', JenisPeristiwa::Kegiatan, $judul, '2026-10-19', null),
        ]));

        foreach (explode("\r\n", $ics) as $baris) {
            expect(strlen($baris))->toBeLessThanOrEqual(75)
                ->and(mb_check_encoding($baris, 'UTF-8'))->toBeTrue();
        }

        expect(str_replace("\r\n ", '', $ics))->toContain('SUMMARY:'.$judul);
    });
});

describe('libur dan jadwal ujian', function () {
    it('memberi peringatan yang tidak menghalangi bila ujian jatuh pada hari libur', function () {
        $ujian = app(JadwalUjianService::class);
        $kelasA = kalKelas($this->prodiA, nama: 'Kalkulus');
        $kelasB = kalKelas($this->prodiB, nama: 'Audit');
        $ruang = kalRuang();

        kalPeristiwa(['judul' => 'Libur Nasional']);
        kalPeristiwa(['judul' => 'Libur Prodi B', 'tanggal_mulai' => '2026-10-20', 'tanggal_selesai' => '2026-10-20', 'prodi_id' => $this->prodiB->id]);
        kalPeristiwa(['judul' => 'Libur Staf', 'tanggal_mulai' => '2026-10-21', 'tanggal_selesai' => '2026-10-21', 'sasaran' => SasaranPeristiwa::Staf]);
        kalPeristiwa(['judul' => 'Libur Draf', 'tanggal_mulai' => '2026-10-22', 'tanggal_selesai' => '2026-10-22', 'status' => StatusPeristiwa::Draf]);

        $data = fn (string $tanggal): JadwalUjianData => JadwalUjianData::fromArray([
            'jenis' => 'uts', 'tanggal' => $tanggal, 'jam_mulai' => '08:00', 'jam_selesai' => '10:00',
            'ruang' => [['ruang_id' => $ruang->id, 'kursi' => 40]],
        ]);

        $libur = fn (Collection $b): Collection => $b->filter(fn (BentrokJadwal $x): bool => $x->jenis === 'libur')->values();

        $padaLibur = $libur($ujian->periksaBentrok($kelasA, $data('2026-10-19')));
        expect($padaLibur)->toHaveCount(1)
            ->and($padaLibur[0]->menghalangi)->toBeFalse()
            ->and($padaLibur[0]->pesan)->toContain('Libur Nasional');

        expect($libur($ujian->periksaBentrok($kelasA, $data('2026-10-20'))))->toBeEmpty()
            ->and($libur($ujian->periksaBentrok($kelasB, $data('2026-10-20'))))->toHaveCount(1)
            ->and($libur($ujian->periksaBentrok($kelasA, $data('2026-10-21'))))->toBeEmpty()
            ->and($libur($ujian->periksaBentrok($kelasA, $data('2026-10-22'))))->toBeEmpty();

        // Laporan semester membacanya juga, dan tetap dapat diterbitkan.
        $sesi = kalUjian($kelasA, '2026-10-19', StatusJadwalUjian::Draf, $ruang);
        expect($libur($ujian->laporanBentrok($this->term)[$sesi->id]))->toHaveCount(1)
            ->and($ujian->terbitkan($this->term, JenisUjianKelas::Uts))->toBe(1);
    });

    it('melewati hari libur saat menyusun jadwal ujian massal', function () {
        $kelas = kalKelas($this->prodiA, nama: 'Fisika');
        kalIkutkan(kalMahasiswa($this->prodiA), $kelas);
        kalRuang();
        kalPeristiwa(['judul' => 'Libur Senin']);

        $rencana = app(JadwalUjianService::class)->susun(new PenyusunanUjianData(
            tahunAkademikId: $this->term->id,
            jenis: JenisUjianKelas::Uts,
            prodiId: null,
            tanggalMulai: '2026-10-19',
            tanggalSelesai: '2026-10-20',
        ));

        expect($rencana->usulan)->toHaveCount(1)
            ->and($rencana->usulan[0]->tanggal)->toBe('2026-10-20');

        $hanyaLibur = app(JadwalUjianService::class)->susun(new PenyusunanUjianData(
            tahunAkademikId: $this->term->id,
            jenis: JenisUjianKelas::Uts,
            prodiId: null,
            tanggalMulai: '2026-10-19',
            tanggalSelesai: '2026-10-19',
        ));

        expect($hanyaLibur->usulan)->toBeEmpty()
            ->and($hanyaLibur->tidakTerjadwal[0]['alasan'])->toBe(__('jadwal_ujian.susun.tanpa_slot'));
    });
});

describe('layar', function () {
    it('membuka tampilan bulan dan daftar di ketiga portal', function () {
        kalPeristiwa(['judul' => 'Libur Nasional']);

        foreach ([
            [kalStaf(), 'staff', '/admin/kalender'],
            [kalDosen(), 'dosen', '/dosen/kalender'],
            [kalMahasiswa($this->prodiA), 'mahasiswa', '/mahasiswa/kalender'],
        ] as [$aktor, $guard, $url]) {
            $this->actingAs($aktor, $guard)->get($url)->assertOk()->assertSee('Libur Nasional')->assertSee('Oktober 2026');
            $this->actingAs($aktor, $guard)->get($url.'?tampilan=daftar&bulan=2026-11')->assertOk()->assertDontSee('Libur Nasional');
            $this->actingAs($aktor, $guard)->get($url.'?bulan=bukan-bulan')->assertOk();
        }
    });

    it('memformat rentang tanggal Indonesia tanpa mengulang yang sama', function () {
        expect(Format::rentangTanggal('2026-10-12', '2026-10-23'))->toBe('12 – 23 Okt 2026')
            ->and(Format::rentangTanggal('2026-10-28', '2026-11-03'))->toBe('28 Okt – 3 Nov 2026')
            ->and(Format::rentangTanggal('2026-12-29', '2027-01-02'))->toBe('29 Des 2026 – 2 Jan 2027')
            ->and(Format::rentangTanggal('2026-10-12', '2026-10-12'))->toBe('12 Okt 2026')
            ->and(Format::bulanTahun('2026-10-01'))->toBe('Oktober 2026');
    });
});

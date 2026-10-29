<?php

declare(strict_types=1);

use App\Enums\EducationLevel;
use App\Enums\SemesterType;
use App\Enums\StudentStatus;
use App\Models\Akademik\KelasKuliah;
use App\Models\Akademik\Krs;
use App\Models\Akademik\KrsDetail;
use App\Models\Akademik\MataKuliah;
use App\Models\Akademik\Nilai;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Kemahasiswaan\StatusMahasiswa;
use App\Models\Kemahasiswaan\Yudisium;
use App\Models\Sdm\Staff;
use App\Services\Kemahasiswaan\KesiapanPisnService;
use Database\Seeders\RolePermissionSeeder;

/**
 * The diploma-number (PISN) pre-flight: every validator the ministry's PISN
 * site applies is visible from local data before the site is opened
 * (ADR-036 §2).
 */
beforeEach(function () {
    $this->term = TahunAkademik::factory()->term(2026, SemesterType::Ganjil)->berjalan()->aktif()->create();
    $this->prodi = Prodi::factory()->create(['kode_pddikti' => 'a1b2c3d4', 'akreditasi' => 'Unggul']);
    $this->pisn = app(KesiapanPisnService::class);
});

/** A graduate whose PDDIKTI record is complete: the baseline everything else deviates from. */
function lulusanSiap(array $mahasiswa = [], array $yudisium = []): Mahasiswa
{
    $m = Mahasiswa::factory()->create(array_merge([
        'prodi_id' => test()->prodi->id,
        'nik' => '3201234567890123',
        'nama_ibu' => 'Siti Aminah',
        'tempat_lahir' => 'Bandung',
        'tanggal_lahir' => '2003-05-12',
        'status' => StudentStatus::Lulus,
        'feeder_id' => 'mhs-1',
        'feeder_registrasi_id' => 'reg-1',
        'feeder_keluar_id' => 'reg-1',
        'term_masuk' => '20261',
    ], $mahasiswa));

    StatusMahasiswa::create([
        'mahasiswa_id' => $m->id, 'tahun_akademik_id' => test()->term->id,
        'status' => 'A', 'semester_ke' => 8, 'sks_kumulatif' => 146, 'ipk' => 3.4, 'is_final' => true,
        'feeder_id' => 'akm-1',
    ]);

    Yudisium::create(array_merge([
        'mahasiswa_id' => $m->id,
        'tahun_akademik_id' => test()->term->id,
        'nomor_sk' => 'SK/123',
        'tanggal_yudisium' => '2026-09-01',
        'tanggal_lulus' => '2026-09-05',
        'total_sks' => 146,
        'ipk' => 3.4,
        'status' => 'ditetapkan',
    ], $yudisium));

    return $m;
}

function nilaiFinalPisn(Mahasiswa $m, ?string $feederId): void
{
    $kelas = KelasKuliah::factory()->create([
        'tahun_akademik_id' => test()->term->id,
        'mata_kuliah_id' => MataKuliah::factory()->create(['prodi_id' => test()->prodi->id])->id,
        'prodi_id' => test()->prodi->id,
    ]);
    $krs = Krs::firstOrCreate(
        ['mahasiswa_id' => $m->id, 'tahun_akademik_id' => test()->term->id],
        ['semester_ke' => 8, 'batas_sks' => 24, 'status' => 'disetujui'],
    );
    $detail = KrsDetail::create(['krs_id' => $krs->id, 'kelas_kuliah_id' => $kelas->id, 'sks' => 3]);

    Nilai::create([
        'krs_detail_id' => $detail->id, 'kelas_kuliah_id' => $kelas->id, 'mahasiswa_id' => $m->id,
        'nilai_angka' => 85, 'nilai_huruf' => 'A', 'bobot' => 4.0, 'is_final' => true, 'feeder_id' => $feederId,
    ]);
}

it('menyatakan siap ketika seluruh catatan sudah sampai di PDDIKTI', function () {
    $m = lulusanSiap();
    nilaiFinalPisn($m, 'nilai-1');

    $hasil = $this->pisn->periksa($m);

    expect($hasil->siap())->toBeTrue()
        ->and($hasil->belumSiap())->toBe([]);
});

it('menangkap lima penolakan tersering PISN, masing-masing dengan alasannya', function () {
    // 1. NIK salah
    $nik = lulusanSiap(['nik' => '12345']);
    // 2. SKS kurang
    $sks = lulusanSiap(['nik' => '3201234567890124'], ['total_sks' => 120]);
    // 3. Masih Aktif di PDDIKTI: Lulus/DO belum terkirim
    $aktif = lulusanSiap(['nik' => '3201234567890125', 'feeder_keluar_id' => null]);
    // 4. Registrasi tidak pernah sampai
    $registrasi = lulusanSiap(['nik' => '3201234567890126', 'feeder_registrasi_id' => null]);
    // 5. Semester yang belum dilaporkan
    $akm = lulusanSiap(['nik' => '3201234567890127']);
    StatusMahasiswa::where('mahasiswa_id', $akm->id)->update(['feeder_id' => null]);

    $hasil = $this->pisn->periksaBanyak(collect([$nik, $sks, $aktif, $registrasi, $akm]));

    expect($hasil[$nik->id]->belumSiap())->toBe(['NIK 16 digit'])
        ->and($hasil[$sks->id]->belumSiap())->toBe(['SKS memenuhi ketentuan (144)'])
        ->and($hasil[$aktif->id]->belumSiap())->toBe(['Status Lulus sudah sampai di PDDIKTI'])
        ->and(collect($hasil[$aktif->id]->rincian)->firstWhere('kode', 'lulus_pddikti')['keterangan'])->toContain('masih Aktif di PDDIKTI')
        ->and($hasil[$registrasi->id]->belumSiap())->toBe(['Riwayat pendidikan tersinkron ke PDDIKTI'])
        ->and($hasil[$akm->id]->belumSiap())->toBe(['Aktivitas kuliah setiap semester tersinkron'])
        ->and(collect($hasil[$akm->id]->rincian)->firstWhere('kode', 'akm')['keterangan'])->toBe('0 dari 1 semester tersinkron');
});

it('menghitung nilai final yang belum terkirim, bukan sekadar ada atau tidak', function () {
    $m = lulusanSiap();
    nilaiFinalPisn($m, 'nilai-1');
    nilaiFinalPisn($m, null);
    nilaiFinalPisn($m, null);

    $rincian = collect($this->pisn->periksa($m)->rincian)->firstWhere('kode', 'nilai');

    expect($rincian['siap'])->toBeFalse()
        ->and($rincian['keterangan'])->toBe('2 nilai belum terkirim');
});

it('menolak yudisium tanpa nomor SK dan identitas PDM yang tidak lengkap', function () {
    $m = lulusanSiap(['nama_ibu' => null], ['nomor_sk' => null]);

    $belum = $this->pisn->periksa($m)->belumSiap();

    expect($belum)->toContain('Yudisium ditetapkan dengan nomor SK')
        ->toContain('Identitas PDM lengkap (nama ibu, tempat & tanggal lahir)');
});

it('memeriksa satu kohort dalam jumlah kueri yang tetap', function () {
    $kohort = collect(range(1, 12))->map(fn (int $i): Mahasiswa => lulusanSiap(['nik' => sprintf('32012345678901%02d', $i)]));

    DB::enableQueryLog();
    $this->pisn->periksaBanyak($kohort);
    $jumlah = count(DB::getQueryLog());
    DB::disableQueryLog();

    // Yudisium, its term, and the prodi are one query each; plus one per
    // loadCount and one for every term record — never one per graduate.
    expect($jumlah)->toBeLessThanOrEqual(9);
});

it('menampilkan kesiapan PISN pada daftar lulusan yang ditetapkan', function () {
    $this->seed(RolePermissionSeeder::class);
    lulusanSiap(['feeder_keluar_id' => null, 'nama' => 'Lulusan Belum Sampai']);

    $staf = Staff::factory()->create();
    $staf->assignRole('super-admin');

    $this->actingAs($staf, 'staff')
        ->get('/admin/yudisium')
        ->assertOk()
        ->assertSee('Siap PISN')
        ->assertSee('1 belum')
        ->assertSee('Status Lulus sudah sampai di PDDIKTI');
});

describe('validator PISN sejak 1 November 2025', function () {
    it('menolak prodi tanpa akreditasi tercatat — satu-satunya validator tanpa eksepsi', function () {
        $this->prodi->update(['akreditasi' => null]);
        $m = lulusanSiap();

        expect($this->pisn->periksa($m)->belumSiap())->toBe(['Program studi terakreditasi']);
    });

    it('memakai SKS dan IPK minimum per jenjang, bukan angka kampus', function () {
        $d3 = Prodi::factory()->create(['jenjang' => EducationLevel::D3, 'akreditasi' => 'Baik']);
        $magister = Prodi::factory()->create(['jenjang' => EducationLevel::S2, 'akreditasi' => 'Baik']);

        // 110 SKS: enough for D3 (108), far below S1 (144).
        $diploma = lulusanSiap(['prodi_id' => $d3->id, 'nik' => '3201234567890131'], ['total_sks' => 110, 'ipk' => 2.10]);
        // IPK 2,90 passes a bachelor, not a master (3,00).
        $s2 = lulusanSiap(['prodi_id' => $magister->id, 'nik' => '3201234567890132', 'term_masuk' => '20251'], ['total_sks' => 40, 'ipk' => 2.90]);

        $hasil = $this->pisn->periksaBanyak(collect([$diploma, $s2]));

        expect($hasil[$diploma->id]->siap())->toBeTrue()
            ->and(collect($hasil[$diploma->id]->rincian)->firstWhere('kode', 'sks')['label'])->toBe('SKS memenuhi ketentuan (108)')
            ->and($hasil[$s2->id]->belumSiap())->toContain('IPK memenuhi ketentuan (3,00)');
    });

    it('menghitung masa studi dari semester masuk sampai semester lulus, cuti ikut terhitung', function () {
        // S1: 16 semesters at most. 20181 -> 20261 is 17.
        $lama = lulusanSiap(['term_masuk' => '20181', 'nik' => '3201234567890141']);
        StatusMahasiswa::where('mahasiswa_id', $lama->id)->delete();
        foreach (['20181', '20261'] as $kode) {
            StatusMahasiswa::create([
                'mahasiswa_id' => $lama->id, 'tahun_akademik_id' => semesterUji($kode)->id,
                'status' => 'A', 'semester_ke' => 1, 'feeder_id' => 'akm-'.$kode,
            ]);
        }

        $rincian = collect($this->pisn->periksa($lama)->rincian)->firstWhere('kode', 'masa_studi');

        expect($rincian['siap'])->toBeFalse()
            ->and($rincian['keterangan'])->toContain('17 semester, batas 16');
    });

    it('menuntut catatan semester pertama pada semester masuknya', function () {
        $m = lulusanSiap(['term_masuk' => '20251']);

        $rincian = collect($this->pisn->periksa($m)->rincian)->firstWhere('kode', 'semester_masuk');

        expect($rincian['siap'])->toBeFalse()
            ->and($rincian['keterangan'])->toBe('masuk 20251, catatan pertama 20261');
    });

    it('memeriksa beban SKS per semester dan semester antara', function () {
        $m = lulusanSiap();
        StatusMahasiswa::where('mahasiswa_id', $m->id)->update(['semester_ke' => 1, 'sks_semester' => 22]);
        StatusMahasiswa::create([
            'mahasiswa_id' => $m->id, 'tahun_akademik_id' => semesterUji('20263')->id,
            'status' => 'A', 'semester_ke' => 2, 'sks_semester' => 10, 'feeder_id' => 'akm-antara',
        ]);

        $rincian = collect($this->pisn->periksa($m)->rincian)->firstWhere('kode', 'beban_semester');

        expect($rincian['siap'])->toBeFalse()
            ->and($rincian['keterangan'])->toBe('melebihi batas pada 20261 (22 SKS), 20263 (10 SKS)');
    });
});

function semesterUji(string $kode): TahunAkademik
{
    return TahunAkademik::query()->where('kode', $kode)->first()
        ?? TahunAkademik::factory()->term((int) substr($kode, 0, 4), SemesterType::from(substr($kode, 4, 1)))->create();
}

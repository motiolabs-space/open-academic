<?php

declare(strict_types=1);

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
use App\Services\Kemahasiswaan\KesiapanPinService;
use Database\Seeders\RolePermissionSeeder;

/**
 * The diploma-number (PIN) pre-flight: every refusal the ministry's portal
 * can give is visible from local data before the portal is opened.
 */
beforeEach(function () {
    $this->term = TahunAkademik::factory()->term(2026, SemesterType::Ganjil)->berjalan()->aktif()->create();
    $this->prodi = Prodi::factory()->create(['kode_pddikti' => 'a1b2c3d4']);
    $this->pin = app(KesiapanPinService::class);
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

function nilaiFinalPin(Mahasiswa $m, ?string $feederId): void
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
    nilaiFinalPin($m, 'nilai-1');

    $hasil = $this->pin->periksa($m);

    expect($hasil->siap())->toBeTrue()
        ->and($hasil->belumSiap())->toBe([]);
});

it('menangkap lima penolakan tersering reservasi PIN, masing-masing dengan alasannya', function () {
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

    $hasil = $this->pin->periksaBanyak(collect([$nik, $sks, $aktif, $registrasi, $akm]));

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
    nilaiFinalPin($m, 'nilai-1');
    nilaiFinalPin($m, null);
    nilaiFinalPin($m, null);

    $rincian = collect($this->pin->periksa($m)->rincian)->firstWhere('kode', 'nilai');

    expect($rincian['siap'])->toBeFalse()
        ->and($rincian['keterangan'])->toBe('2 nilai belum terkirim');
});

it('menolak yudisium tanpa nomor SK dan identitas PDM yang tidak lengkap', function () {
    $m = lulusanSiap(['nama_ibu' => null], ['nomor_sk' => null]);

    $belum = $this->pin->periksa($m)->belumSiap();

    expect($belum)->toContain('Yudisium ditetapkan dengan nomor SK')
        ->toContain('Identitas PDM lengkap (nama ibu, tempat & tanggal lahir)');
});

it('memeriksa satu kohort dalam jumlah kueri yang tetap', function () {
    $kohort = collect(range(1, 12))->map(fn (int $i): Mahasiswa => lulusanSiap(['nik' => sprintf('32012345678901%02d', $i)]));

    DB::enableQueryLog();
    $this->pin->periksaBanyak($kohort);
    $jumlah = count(DB::getQueryLog());
    DB::disableQueryLog();

    // Yudisium is already loaded by the caller in practice; here it costs one
    // query, plus one per loadCount — never one per graduate.
    expect($jumlah)->toBeLessThanOrEqual(6);
});

it('menampilkan kesiapan PIN pada daftar lulusan yang ditetapkan', function () {
    $this->seed(RolePermissionSeeder::class);
    lulusanSiap(['feeder_keluar_id' => null, 'nama' => 'Lulusan Belum Sampai']);

    $staf = Staff::factory()->create();
    $staf->assignRole('super-admin');

    $this->actingAs($staf, 'staff')
        ->get('/admin/yudisium')
        ->assertOk()
        ->assertSee('Siap PIN')
        ->assertSee('1 belum')
        ->assertSee('Status Lulus sudah sampai di PDDIKTI');
});

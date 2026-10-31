<?php

declare(strict_types=1);

use App\Enums\EducationLevel;
use App\Models\Akademik\Fakultas;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\Staff;
use App\Models\System\InstallationPing;
use App\Models\System\LogAktivitas;
use App\Models\System\Setting;
use App\Services\Branding\BrandingService;
use App\Support\Instalasi;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

/**
 * The first-run wizard (ADR-043).
 *
 * The lock is the feature. A wizard that creates a super-admin and stays
 * reachable afterwards is an open door, so most cases below are about when
 * /setup must answer 404 — and the fresh database every test starts with
 * (no accounts, no marker) is exactly a fresh installation.
 */
beforeEach(fn () => instalasiBaru());

it('mengalihkan setiap rute web ke setup pada instalasi baru', function () {
    $this->get('/')->assertRedirect(route('setup.formulir'));
    $this->get(route('login'))->assertRedirect(route('setup.formulir'));
    $this->get('/admin')->assertRedirect(route('setup.formulir'));

    $html = $this->get(route('setup.formulir'))
        ->assertOk()
        ->assertSee(__('instalasi.judul'))
        ->assertSee(config('instalasi.telemetri.endpoint'))
        ->getContent();

    // Consent is never pre-ticked: the only checked state comes from old().
    expect($html)->toContain('name="telemetri_setuju" value="1"')
        ->not->toMatch('/name="telemetri_setuju" value="1"[^>]*checked/');
});

it('tidak mengalihkan rute API', function () {
    // API consumers authenticate with tokens; a redirect to an HTML wizard is
    // never a useful answer to them.
    $this->getJson('/api/bridge/v1/students')->assertUnauthorized();
});

it('menyelesaikan setup: identitas, super admin, struktur awal, lalu terkunci', function () {
    Storage::fake('public');

    $this->post(route('setup.simpan'), isianSetup([
        'logo' => UploadedFile::fake()->image('logo.png', 120, 120),
        'fakultas_kode' => 'FT',
        'fakultas_nama' => 'Fakultas Teknik',
        'prodi_kode' => 'TI',
        'prodi_nama' => 'Teknik Informatika',
        'prodi_jenjang' => EducationLevel::S1->value,
        'tahun_mulai' => 2026,
        'semester' => '1',
        'tanggal_mulai' => '2026-09-01',
        'tanggal_selesai' => '2027-01-31',
    ]))
        ->assertRedirect(route('login'))
        ->assertSessionHas('sukses', __('instalasi.selesai'));

    $brand = app(BrandingService::class);

    expect($brand->institutionName())->toBe('Institut Teknologi Lembah Hijau')
        ->and($brand->institutionShortName())->toBe('ITLH')
        ->and($brand->institutionCode())->toBe('041234')
        ->and($brand->institutionCity())->toBe('Salatiga')
        ->and($brand->institutionProvince())->toBe('Jawa Tengah')
        ->and($brand->institutionEmail())->toBe('info@itlh.ac.id')
        // Through the existing branding keys, normalised so the CSS variables
        // do not depend on how the colour picker cased them.
        ->and($brand->primaryColor())->toBe('#123456')
        ->and($brand->accentColor())->toBe('#ABCDEF')
        ->and(Setting::get('dokumen', 'kop_alamat'))->toBe('Jl. Merbabu No. 7, Salatiga, Jawa Tengah');

    $logo = Setting::get('branding', 'logo_path');
    Storage::disk('public')->assertExists($logo);

    $admin = Staff::sole();
    expect($admin->email)->toBe('ratna@itlh.ac.id')
        ->and($admin->hasRole('super-admin'))->toBeTrue()
        ->and(Hash::check('rahasia-kampus-1', $admin->password))->toBeTrue();

    $prodi = Prodi::sole();
    expect(Fakultas::sole()->nama)->toBe('Fakultas Teknik')
        ->and($prodi->fakultas->kode)->toBe('FT')
        ->and($prodi->jenjang)->toBe(EducationLevel::S1);

    $term = TahunAkademik::sole();
    expect($term->kode)->toBe('20261')->and($term->is_active)->toBeTrue();

    expect(Instalasi::selesaiPada())->not->toBeNull()
        ->and(Instalasi::perluSetup())->toBeFalse();

    // Locked: both verbs 404, and the rest of the application is reachable.
    $this->get(route('setup.formulir'))->assertNotFound();
    $this->post(route('setup.simpan'), isianSetup(['admin_email' => 'penyusup@contoh.test']))->assertNotFound();
    $this->get(route('login'))->assertOk();

    expect(Staff::count())->toBe(1);

    // The completion is on the audit trail, filed under the installation.
    expect(LogAktivitas::query()->where('event', 'installation_setup')->where('causer_id', $admin->id)->exists())
        ->toBeTrue();
});

it('menyelesaikan setup tanpa struktur awal yang opsional', function () {
    $this->post(route('setup.simpan'), isianSetup())->assertRedirect(route('login'));

    expect(Staff::count())->toBe(1)
        ->and(Fakultas::count())->toBe(0)
        ->and(TahunAkademik::count())->toBe(0);
});

it('membuat peran bila operator hanya menjalankan migrate tanpa seeder', function () {
    // Fresh test databases have no roles, which is the `migrate`-only case.
    expect(Role::count())->toBe(0);

    $this->post(route('setup.simpan'), isianSetup())->assertRedirect(route('login'));

    expect(Staff::sole()->hasPermissionTo('pengaturan.manage', 'staff'))->toBeTrue();
});

it('menolak isian tidak sah tanpa meninggalkan apa pun', function (array $ganti, string $medan) {
    $this->from(route('setup.formulir'))
        ->post(route('setup.simpan'), isianSetup($ganti))
        ->assertRedirect(route('setup.formulir'))
        ->assertSessionHasErrors($medan);

    expect(Staff::count())->toBe(0)
        ->and(Instalasi::perluSetup())->toBeTrue()
        ->and(Setting::get('branding', 'institution_name'))->toBeNull();
})->with([
    'nama kosong' => [['nama_pt' => ''], 'nama_pt'],
    'kode PT bukan enam digit' => [['kode_pt' => '41234A'], 'kode_pt'],
    'surel tidak sah' => [['surel' => 'bukan-surel'], 'surel'],
    'warna bukan heks' => [['warna_utama' => 'navy'], 'warna_utama'],
    'kata sandi tak sama' => [['admin_password_confirmation' => 'lain-sama-sekali'], 'admin_password'],
    'kata sandi pendek' => [['admin_password' => 'pendek', 'admin_password_confirmation' => 'pendek'], 'admin_password'],
    'prodi tanpa fakultas' => [['prodi_kode' => 'TI', 'prodi_nama' => 'Informatika', 'prodi_jenjang' => '30'], 'fakultas_nama'],
    'semester tanpa tanggal' => [['tahun_mulai' => 2026, 'semester' => '1'], 'tanggal_mulai'],
    'semester terbalik' => [['tahun_mulai' => 2026, 'semester' => '1', 'tanggal_mulai' => '2026-09-01', 'tanggal_selesai' => '2026-08-01'], 'tanggal_selesai'],
    'logo SVG' => [['logo' => UploadedFile::fake()->create('logo.svg', 4, 'image/svg+xml')], 'logo'],
]);

it('tertutup bagi instalasi lama yang sudah punya akun tanpa penanda', function (string $model) {
    // A campus that installed before the wizard existed pulls the update: it
    // must not be offered a fresh super-admin by whoever opens /setup first.
    $model::factory()->create();

    expect(Instalasi::selesaiPada())->toBeNull();

    $this->get(route('setup.formulir'))->assertNotFound();
    $this->post(route('setup.simpan'), isianSetup())->assertNotFound();
    $this->get(route('login'))->assertOk();
})->with([
    'staf' => Staff::class,
    'dosen' => Dosen::class,
    'mahasiswa' => Mahasiswa::class,
]);

it('tertutup bagi kampus demo', function () {
    // DemoCampusSeeder writes the marker next to the demo marker; asserting
    // the marker alone keeps this from seeding ninety tables.
    Instalasi::tandaiSelesai();

    $this->get(route('setup.formulir'))->assertNotFound();
    $this->get(route('login'))->assertOk();
});

it('hanya dapat dibuka ulang lewat artisan', function () {
    $this->post(route('setup.simpan'), isianSetup());
    $this->get(route('setup.formulir'))->assertNotFound();

    $this->artisan('openacademic:setup-ulang')
        ->expectsConfirmation('Buka kembali wizard setup?', 'yes')
        ->assertSuccessful();

    $this->get(route('setup.formulir'))->assertOk();

    // Running it again creates a second super-admin and locks again.
    $this->post(route('setup.simpan'), isianSetup(['admin_email' => 'kedua@itlh.ac.id']))
        ->assertRedirect(route('login'));

    expect(Staff::count())->toBe(2)
        ->and(Instalasi::perluSetup())->toBeFalse()
        ->and(LogAktivitas::query()->where('event', 'installation_setup_reopened')->exists())->toBeTrue();
});

it('tidak mengirim apa pun ke Humanix bila kotak persetujuan tidak dicentang', function () {
    $this->post(route('setup.simpan'), isianSetup())->assertRedirect(route('login'));

    expect(InstallationPing::count())->toBe(0);
});

<?php

declare(strict_types=1);

use App\DTOs\Instalasi\PersetujuanTelemetriData;
use App\Enums\PeristiwaInstalasi;
use App\Enums\StatusPingInstalasi;
use App\Jobs\KirimPingInstalasiJob;
use App\Models\Akademik\TahunAkademik;
use App\Models\Sdm\Staff;
use App\Models\System\InstallationPing;
use App\Models\System\LogAktivitas;
use App\Models\System\Setting;
use App\Services\Instalasi\Contracts\InstalasiReporterInterface;
use App\Services\Instalasi\HttpInstalasiReporter;
use App\Services\Instalasi\InstalasiReporterPalsu;
use App\Services\Instalasi\TelemetriService;
use App\Support\Instalasi;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\Events\NoPendingMigrations;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

/**
 * The installation ping to Humanix (ADR-043).
 *
 * Everything here is a promise made on the consent screen: nothing without a
 * tick, exactly these fields, .env beats the screen, a dead endpoint never
 * breaks anything, and one ping per version.
 */
beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 10, 31)->setTime(9, 0));

    // A set-up campus with identity, as the wizard leaves it.
    Instalasi::tandaiSelesai();
    foreach ([
        'institution_name' => 'Institut Teknologi Lembah Hijau',
        'institution_code' => '041234',
        'institution_city' => 'Salatiga',
        'institution_province' => 'Jawa Tengah',
        'institution_address' => 'Jl. Merbabu No. 7',
        'institution_email' => 'info@itlh.ac.id',
    ] as $kunci => $nilai) {
        Setting::put('branding', $kunci, $nilai);
    }

    $this->reporter = app(InstalasiReporterInterface::class);
    $this->telemetri = app(TelemetriService::class);
});

function setujuiTelemetri(bool $sertakanUrl = false): void
{
    app(TelemetriService::class)->atur(new PersetujuanTelemetriData(true, $sertakanUrl));
}

it('memakai reporter palsu di suite, tidak pernah jaringan', function () {
    expect($this->reporter)->toBeInstanceOf(InstalasiReporterPalsu::class);
});

it('tidak mengirim apa pun tanpa persetujuan', function () {
    expect($this->telemetri->laporkan())->toBeNull()
        ->and($this->reporter->terkirim)->toBe([])
        ->and(InstallationPing::count())->toBe(0);

    // Not even when the environment variable says true: it can only take
    // consent away, never give it.
    config(['instalasi.telemetri.aktif' => true]);

    expect($this->telemetri->laporkan())->toBeNull()
        ->and($this->reporter->terkirim)->toBe([]);
});

it('mengirim muatan persis seperti yang dijanjikan layar persetujuan', function () {
    setujuiTelemetri();

    $id = Setting::get(Instalasi::GRUP, 'id_instalasi');

    expect(Str::isUuid($id))->toBeTrue()
        ->and($this->reporter->terkirim)->toBe([[
            'installation_id' => $id,
            'event' => 'installed',
            'institution_name' => 'Institut Teknologi Lembah Hijau',
            'institution_code' => '041234',
            'city' => 'Salatiga',
            'province' => 'Jawa Tengah',
            'app_version' => config('instalasi.versi'),
            'php_version' => PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION,
            'db_engine' => 'sqlite',
            'date' => '2026-10-31',
        ]]);

    // Nothing about the campus beyond that: no address, no e-mail, no key,
    // no URL — and the id is not derived from the institution code.
    $isi = json_encode($this->reporter->terkirim);
    expect($isi)->not->toContain('Merbabu')
        ->not->toContain('info@itlh.ac.id')
        ->not->toContain((string) config('app.key'))
        ->not->toContain((string) config('app.url'));

    $ping = InstallationPing::sole();
    expect($ping->status)->toBe(StatusPingInstalasi::Sent)
        ->and($ping->event)->toBe(PeristiwaInstalasi::Dipasang)
        ->and($ping->payload)->toBe($this->reporter->terkirim[0])
        ->and($ping->attempts)->toBe(1)
        ->and($ping->sent_at)->not->toBeNull();
});

it('menyertakan alamat aplikasi hanya bila kampus mencentangnya', function () {
    setujuiTelemetri(sertakanUrl: true);

    expect($this->reporter->terkirim[0]['app_url'])->toBe(config('app.url'));
});

it('mengutamakan HUMANIX_TELEMETRI=false di atas persetujuan layar', function () {
    config(['instalasi.telemetri.aktif' => false]);

    setujuiTelemetri();

    expect($this->telemetri->disetujui())->toBeTrue()
        ->and($this->telemetri->aktif())->toBeFalse()
        ->and($this->reporter->terkirim)->toBe([])
        ->and(InstallationPing::count())->toBe(0);
});

it('membaca "false" dari .env sebagai mati, bukan sebagai teks yang bernilai benar', function (mixed $nilai, bool $diizinkan) {
    config(['instalasi.telemetri.aktif' => $nilai]);

    expect($this->telemetri->diizinkanEnv())->toBe($diizinkan);
})->with([
    [false, false],
    ['false', false],
    ['0', false],
    ['off', false],
    [true, true],
    [null, true],
]);

it('membatalkan kabar yang sudah antre bila persetujuan dicabut sebelum terkirim', function () {
    Queue::fake();
    setujuiTelemetri();

    $ping = InstallationPing::sole();
    expect($ping->status)->toBe(StatusPingInstalasi::Queued);

    $this->telemetri->atur(new PersetujuanTelemetriData(false));

    (new KirimPingInstalasiJob($ping->id))->handle($this->telemetri, $this->reporter);

    expect($ping->fresh()->status)->toBe(StatusPingInstalasi::Cancelled)
        ->and($this->reporter->terkirim)->toBe([]);
});

it('tidak melempar galat dan tidak menggagalkan setup ketika Humanix tak terjangkau', function () {
    $this->reporter->gagalkan('cURL error 6: Could not resolve host');

    // Through the whole wizard, on the sync queue the suite uses: the request
    // that sends the ping is the request that finishes setup.
    Setting::put(Instalasi::GRUP, Instalasi::SELESAI, '');

    $this->post(route('setup.simpan'), isianSetup([
        'telemetri_setuju' => '1',
    ]))->assertRedirect(route('login'));

    expect(Staff::count())->toBe(1)
        ->and(Instalasi::perluSetup())->toBeFalse();

    $ping = InstallationPing::sole();
    expect($ping->status)->toBe(StatusPingInstalasi::Failed)
        ->and($ping->attempts)->toBe(1)
        ->and($ping->error)->toContain('Could not resolve host');
});

it('menjadwalkan ulang kabar yang gagal dengan jeda, lalu berhenti', function () {
    config(['queue.default' => 'database']);
    Queue::fake();

    $this->reporter->gagalkan();
    setujuiTelemetri();

    $ping = InstallationPing::sole();
    $job = new KirimPingInstalasiJob($ping->id);

    $job->handle($this->telemetri, $this->reporter);
    expect($ping->fresh()->attempts)->toBe(1);
    Queue::assertPushed(KirimPingInstalasiJob::class, 2); // the first, then one retry

    $job->handle($this->telemetri, $this->reporter);
    $job->handle($this->telemetri, $this->reporter);

    // Three attempts is the ceiling: the third failure is final.
    expect($ping->fresh()->attempts)->toBe(3)
        ->and($ping->fresh()->status)->toBe(StatusPingInstalasi::Failed);
    Queue::assertPushed(KirimPingInstalasiJob::class, 3);
});

it('mengirim sekali per versi, dan sekali lagi hanya saat versi berubah', function () {
    setujuiTelemetri();

    $this->telemetri->laporkan();
    $this->telemetri->laporkan();
    event(new NoPendingMigrations('up')); // `migrate` on an unchanged release

    expect($this->reporter->terkirim)->toHaveCount(1)
        ->and(InstallationPing::count())->toBe(1);

    config(['instalasi.versi' => '9.9.9']);
    event(new NoPendingMigrations('up')); // `migrate` after an upgrade

    expect($this->reporter->terkirim)->toHaveCount(2)
        ->and($this->reporter->terkirim[1]['event'])->toBe('upgraded')
        ->and($this->reporter->terkirim[1]['app_version'])->toBe('9.9.9')
        // Same installation, same id.
        ->and($this->reporter->terkirim[1]['installation_id'])->toBe($this->reporter->terkirim[0]['installation_id'])
        ->and(InstallationPing::count())->toBe(2);
});

it('mengirim ulang kabar yang dulu gagal untuk versi yang sama, tanpa baris ganda', function () {
    $this->reporter->gagalkan();
    setujuiTelemetri();
    expect(InstallationPing::sole()->status)->toBe(StatusPingInstalasi::Failed);

    app()->instance(InstalasiReporterInterface::class, $palsu = new InstalasiReporterPalsu);
    $this->telemetri->laporkan();

    expect($palsu->terkirim)->toHaveCount(1)
        ->and(InstallationPing::sole()->status)->toBe(StatusPingInstalasi::Sent);
});

it('memposting JSON ke endpoint yang dikonfigurasi lewat reporter HTTP', function () {
    Http::fake(['humanix.test/*' => Http::response(['ok' => true], 202)]);
    config(['instalasi.telemetri.endpoint' => 'https://humanix.test/api/ping']);

    $hasil = (new HttpInstalasiReporter)->kirim(['installation_id' => 'x', 'event' => 'installed']);

    expect($hasil->berhasil)->toBeTrue()->and($hasil->statusHttp)->toBe(202);

    Http::assertSent(fn (Request $r): bool => $r->url() === 'https://humanix.test/api/ping'
        && $r->method() === 'POST'
        && $r->data() === ['installation_id' => 'x', 'event' => 'installed']
        && str_starts_with($r->header('User-Agent')[0] ?? '', 'OpenAcademic/'));
});

it('mengubah jawaban 5xx dan putus koneksi menjadi hasil gagal, bukan exception', function () {
    Http::fake([
        'humanix.test/mati' => Http::response('down', 503),
        'humanix.test/putus' => fn () => throw new ConnectionException('Connection timed out'),
    ]);

    config(['instalasi.telemetri.endpoint' => 'https://humanix.test/mati']);
    $mati = (new HttpInstalasiReporter)->kirim([]);

    config(['instalasi.telemetri.endpoint' => 'https://humanix.test/putus']);
    $putus = (new HttpInstalasiReporter)->kirim([]);

    expect($mati->berhasil)->toBeFalse()->and($mati->statusHttp)->toBe(503)
        ->and($putus->berhasil)->toBeFalse()->and($putus->galat)->toContain('timed out');
});

it('dapat dinyalakan dan dimatikan dari Pengaturan, tercatat di log aktivitas', function () {
    $this->seed(RolePermissionSeeder::class);
    TahunAkademik::factory()->berjalan()->create(['is_active' => true]);
    $admin = Staff::factory()->create();
    $admin->assignRole('super-admin');

    $this->actingAs($admin, 'staff')
        ->get(route('admin.pengaturan'))
        ->assertOk()
        ->assertSee(__('instalasi.telemetri.judul'))
        ->assertSee(__('instalasi.telemetri.riwayat_kosong'));

    $this->actingAs($admin, 'staff')
        ->put(route('admin.pengaturan.telemetri'), ['telemetri_setuju' => '1'])
        ->assertSessionHas('sukses', __('instalasi.telemetri.disimpan'));

    expect($this->telemetri->aktif())->toBeTrue()
        ->and($this->reporter->terkirim)->toHaveCount(1);

    $this->actingAs($admin, 'staff')
        ->put(route('admin.pengaturan.telemetri'), ['telemetri_setuju' => '0', 'telemetri_sertakan_url' => '1'])
        ->assertSessionHas('sukses');

    // The URL cannot outlive consent to the ping it rides on.
    expect($this->telemetri->aktif())->toBeFalse()
        ->and($this->telemetri->sertakanUrl())->toBeFalse();

    expect(LogAktivitas::query()
        ->where('event', 'telemetry_consent_changed')
        ->where('causer_id', $admin->id)
        ->count())->toBe(2);

    $this->actingAs($admin, 'staff')
        ->get(route('admin.pengaturan'))
        ->assertSee(StatusPingInstalasi::Sent->label());
});

it('menolak perubahan persetujuan oleh staf tanpa izin pengaturan', function () {
    $this->seed(RolePermissionSeeder::class);
    TahunAkademik::factory()->berjalan()->create(['is_active' => true]);
    $staf = Staff::factory()->create();
    $staf->assignRole('keuangan');

    $this->actingAs($staf, 'staff')
        ->put(route('admin.pengaturan.telemetri'), ['telemetri_setuju' => '1'])
        ->assertForbidden();

    expect($this->telemetri->disetujui())->toBeFalse();
});

it('memberi tahu di layar bila .env mematikannya', function () {
    $this->seed(RolePermissionSeeder::class);
    TahunAkademik::factory()->berjalan()->create(['is_active' => true]);
    $admin = Staff::factory()->create();
    $admin->assignRole('super-admin');

    config(['instalasi.telemetri.aktif' => false]);

    $this->actingAs($admin, 'staff')
        ->get(route('admin.pengaturan'))
        ->assertSee(__('instalasi.telemetri.dimatikan_env'))
        ->assertDontSee('name="telemetri_setuju" value="1"', false);
});

it('menampilkan isi kabar tanpa mengirim apa pun lewat artisan', function () {
    $this->artisan('openacademic:telemetri')
        ->expectsOutputToContain('"institution_code": "041234"')
        ->assertSuccessful();

    expect($this->reporter->terkirim)->toBe([])
        // Looking does not mint the installation id.
        ->and(Setting::get(Instalasi::GRUP, 'id_instalasi'))->toBeNull();
});

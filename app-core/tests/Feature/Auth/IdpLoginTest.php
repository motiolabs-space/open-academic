<?php

declare(strict_types=1);

use App\Models\Sdm\Dosen;
use App\Models\Sdm\Staff;
use App\Models\System\LogAktivitas;
use App\Services\Auth\DuaFaktorService;
use App\Services\Auth\Idp\FakeIdentityProvider;
use App\Services\Auth\Totp;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;

/**
 * Masuk lewat IdP kampus, melawan penyedia palsu.
 *
 * Penyedia palsunya menjalankan validasi klaim yang sama dengan driver OIDC,
 * jadi yang diuji di sini bukan "apakah fake-nya bekerja" melainkan kebijakan
 * di belakangnya: siapa yang boleh masuk, bagaimana akun ditautkan, dan apa
 * yang terjadi pada faktor kedua. phpunit.xml menyalakan IDP_ENABLED karena
 * rutenya didaftarkan saat boot — config() di dalam tes sudah terlambat.
 */
beforeEach(function () {
    $this->idp = app(FakeIdentityProvider::class);
});

/** Leg pertama: minta pengalihan, kembalikan state/nonce yang disimpan di sesi. */
function mulaiMasukIdp(): array
{
    test()->get('/masuk/idp')->assertRedirect();

    return session('idp');
}

/**
 * Leg kedua: IdP "mengembalikan" klaim ini untuk satu kode, lalu browser
 * mendarat di callback dengan state yang benar — kecuali diganti.
 *
 * @param array<string, mixed> $claims
 */
function kembaliDariIdp(array $claims, ?string $state = null, string $kode = 'kode-uji'): TestResponse
{
    $sesi = mulaiMasukIdp();

    test()->idp->akanMengembalikan($kode, $claims);

    return test()->get('/masuk/idp/callback?'.http_build_query([
        'code' => $kode,
        'state' => $state ?? $sesi['state'],
    ]));
}

function klaimStaf(string $email = 'staf@kampus.test', string $sub = 'sub-staf-1'): array
{
    return ['sub' => $sub, 'email' => $email, 'email_verified' => true, 'name' => 'Staf Uji'];
}

describe('halaman masuk', function () {
    it('menampilkan tombol IdP hanya bila aktif', function () {
        $this->get('/masuk')->assertOk()->assertSee('Masuk dengan Akun Kampus Uji');

        config(['idp.enabled' => false]);

        $this->get('/masuk')->assertOk()->assertDontSee('Masuk dengan Akun Kampus Uji');
    });

    it('mengarahkan ke IdP dengan state dan nonce yang disimpan di sesi', function () {
        $respons = $this->get('/masuk/idp');

        $sesi = session('idp');

        expect($sesi['state'])->toBeString()->not->toBe('')
            ->and($sesi['nonce'])->toBeString()->not->toBe('')
            ->and($sesi['state'])->not->toBe($sesi['nonce']);

        $tujuan = $respons->headers->get('Location');

        expect($tujuan)->toStartWith('https://idp.uji.test/authorize?')
            ->toContain('state='.$sesi['state'])
            ->toContain('nonce='.$sesi['nonce'])
            ->toContain('client_id=open-academic-uji')
            ->toContain(urlencode(route('idp.callback')));
    });

    it('tidak mendaftarkan rutenya saat IDP_ENABLED=false', function () {
        /*
         * Rute dibaca saat boot, jadi diperiksa pada proses yang di-boot dengan
         * env itu — bukan lewat config() di proses ini, yang rutenya sudah
         * terlanjur ada. Variabel yang diberikan ke proses anak mengalahkan
         * .env karena Dotenv tidak menimpa yang sudah ada.
         */
        expect(Route::has('idp.redirect'))->toBeTrue();

        $hasil = Process::path(base_path())
            ->env(['IDP_ENABLED' => 'false'])
            ->run([PHP_BINARY, 'artisan', 'route:list', '--json']);

        expect($hasil->successful())->toBeTrue($hasil->errorOutput());

        $uri = collect(json_decode($hasil->output(), true))->pluck('uri');

        expect($uri)->toContain('masuk')
            ->and($uri->filter(fn ($u) => str_starts_with((string) $u, 'masuk/idp')))->toBeEmpty();
    });
});

describe('penautan akun', function () {
    it('memasukkan staf yang surelnya cocok dan menautkan subject-nya', function () {
        $staf = Staff::factory()->create(['email' => 'staf@kampus.test']);

        kembaliDariIdp(klaimStaf())->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($staf, 'staff');

        $staf->refresh();

        expect($staf->idp_subject)->toBe('sub-staf-1')
            ->and($staf->last_login_at)->not->toBeNull();

        expect(LogAktivitas::where('event', 'idp_masuk')->where('subject_id', $staf->id)->exists())->toBeTrue();
    });

    it('mencocokkan surel tanpa peduli huruf besar', function () {
        $staf = Staff::factory()->create(['email' => 'Staf@Kampus.test']);

        kembaliDariIdp(klaimStaf('STAF@kampus.test'))->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($staf, 'staff');
    });

    it('memakai subject pada masuk berikutnya, bukan surel', function () {
        // Kotak suratnya sudah diganti nama di direktori; akunnya tetap miliknya.
        $staf = Staff::factory()->create(['email' => 'lama@kampus.test', 'idp_subject' => 'sub-staf-1']);

        kembaliDariIdp(klaimStaf('baru@kampus.test'))->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($staf, 'staff');
    });

    it('menolak surel yang tidak dikenal dan mencatatnya tanpa kodenya', function () {
        kembaliDariIdp(klaimStaf('tamu@kampus.test'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('identitas');

        $this->assertGuest('staff');

        expect(Staff::count())->toBe(0);

        $log = LogAktivitas::where('event', 'idp_gagal')->firstOrFail();

        expect($log->description)->toContain('tidak_dikenal')
            ->and($log->subject_label)->toBe('tamu@kampus.test')
            ->and(json_encode($log->getAttributes()))->not->toContain('kode-uji');
    });

    it('membuat akun staf tanpa peran hanya bila provisioning dinyalakan', function () {
        config(['idp.provision' => true]);

        kembaliDariIdp(klaimStaf('baru@kampus.test', 'sub-baru'))->assertRedirect(route('admin.dashboard'));

        $staf = Staff::where('email', 'baru@kampus.test')->firstOrFail();

        expect($staf->idp_subject)->toBe('sub-baru')
            ->and($staf->nama)->toBe('Staf Uji')
            ->and($staf->roles)->toBeEmpty();

        $this->assertAuthenticatedAs($staf, 'staff');
    });

    it('menolak akun yang dinonaktifkan', function () {
        Staff::factory()->nonAktif()->create(['email' => 'mantan@kampus.test', 'idp_subject' => 'sub-mantan']);

        kembaliDariIdp(klaimStaf('mantan@kampus.test', 'sub-mantan'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('identitas');

        $this->assertGuest('staff');

        expect(LogAktivitas::where('event', 'idp_gagal')->value('description'))->toContain('nonaktif');
    });

    it('menolak surel yang belum diverifikasi IdP', function () {
        Staff::factory()->create(['email' => 'staf@kampus.test']);

        kembaliDariIdp(array_replace(klaimStaf(), ['email_verified' => false]))
            ->assertRedirect(route('login'));

        $this->assertGuest('staff');

        // Kecuali kampus melonggarkannya dengan sadar (Entra ID).
        config(['idp.require_verified_email' => false]);

        kembaliDariIdp(['sub' => 'sub-staf-1', 'email' => 'staf@kampus.test'])
            ->assertRedirect(route('admin.dashboard'));
    });

    it('menolak surel yang akunnya sudah tertaut ke subject lain', function () {
        Staff::factory()->create(['email' => 'staf@kampus.test', 'idp_subject' => 'sub-asli']);

        kembaliDariIdp(klaimStaf('staf@kampus.test', 'sub-penyusup'))
            ->assertRedirect(route('login'));

        $this->assertGuest('staff');

        expect(LogAktivitas::where('event', 'idp_gagal')->value('description'))->toContain('tertaut_identitas_lain');
    });

    it('tidak memasukkan dosen kecuali guard-nya diizinkan', function () {
        $dosen = Dosen::factory()->create(['email' => 'dosen@kampus.test']);

        kembaliDariIdp(klaimStaf('dosen@kampus.test', 'sub-dosen'))->assertRedirect(route('login'));

        $this->assertGuest('dosen');

        config(['idp.guards' => ['staff', 'dosen']]);

        kembaliDariIdp(klaimStaf('dosen@kampus.test', 'sub-dosen'))->assertRedirect(route('dosen.dashboard'));

        $this->assertAuthenticatedAs($dosen, 'dosen');
        $this->assertGuest('staff');
    });

    it('menolak surel yang ada di dua tabel alih-alih memilih salah satunya', function () {
        config(['idp.guards' => ['staff', 'dosen']]);

        Staff::factory()->create(['email' => 'rangkap@kampus.test']);
        Dosen::factory()->create(['email' => 'rangkap@kampus.test']);

        kembaliDariIdp(klaimStaf('rangkap@kampus.test', 'sub-rangkap'))->assertRedirect(route('login'));

        $this->assertGuest('staff');
        $this->assertGuest('dosen');

        expect(LogAktivitas::where('event', 'idp_gagal')->value('description'))->toContain('ambigu');
    });
});

describe('verifikasi callback', function () {
    beforeEach(function () {
        Staff::factory()->create(['email' => 'staf@kampus.test']);
    });

    it('menolak state yang tidak cocok', function () {
        kembaliDariIdp(klaimStaf(), state: 'state-palsu')->assertRedirect(route('login'));

        $this->assertGuest('staff');

        expect(LogAktivitas::where('event', 'idp_gagal')->value('description'))->toContain('state_salah');
    });

    it('menolak callback tanpa sesi yang menunggu', function () {
        $this->get('/masuk/idp/callback?code=apa-saja&state=apa-saja')->assertRedirect(route('login'));

        $this->assertGuest('staff');
    });

    it('menolak callback yang diputar ulang', function () {
        kembaliDariIdp(klaimStaf())->assertRedirect(route('admin.dashboard'));

        $this->post('/keluar');

        // URL yang sama persis, kedua kalinya: state-nya sudah dikonsumsi.
        $this->get('/masuk/idp/callback?code=kode-uji&state=bekas')->assertRedirect(route('login'));

        $this->assertGuest('staff');
    });

    it('menolak ID token dari penerbit lain', function () {
        kembaliDariIdp(klaimStaf() + ['iss' => 'https://penyusup.test'])->assertRedirect(route('login'));

        $this->assertGuest('staff');

        expect(LogAktivitas::where('event', 'idp_gagal')->value('description'))->toContain('iss_salah');
    });

    it('menolak ID token untuk klien lain', function () {
        kembaliDariIdp(klaimStaf() + ['aud' => 'aplikasi-lain'])->assertRedirect(route('login'));

        $this->assertGuest('staff');

        expect(LogAktivitas::where('event', 'idp_gagal')->value('description'))->toContain('aud_salah');
    });

    it('menolak ID token yang nonce-nya bukan milik percobaan ini', function () {
        kembaliDariIdp(klaimStaf() + ['nonce' => 'nonce-lain'])->assertRedirect(route('login'));

        $this->assertGuest('staff');

        expect(LogAktivitas::where('event', 'idp_gagal')->value('description'))->toContain('nonce_salah');
    });

    it('menolak ID token yang kedaluwarsa', function () {
        kembaliDariIdp(klaimStaf() + ['exp' => now()->subMinutes(5)->timestamp])->assertRedirect(route('login'));

        $this->assertGuest('staff');
    });

    it('mencatat penolakan dari IdP tanpa meneruskan deskripsinya', function () {
        $sesi = mulaiMasukIdp();

        $this->get('/masuk/idp/callback?'.http_build_query([
            'error' => 'access_denied',
            'error_description' => 'code=rahasia-yang-tak-boleh-tercatat',
            'state' => $sesi['state'],
        ]))->assertRedirect(route('login'));

        $log = LogAktivitas::where('event', 'idp_gagal')->firstOrFail();

        expect($log->description)->toContain('ditolak_idp')->toContain('access_denied')
            ->not->toContain('rahasia-yang-tak-boleh-tercatat');
    });
});

describe('titik temu dengan verifikasi dua langkah', function () {
    beforeEach(function () {
        $this->staf = Staff::factory()->create(['email' => 'staf@kampus.test']);

        $layanan = app(DuaFaktorService::class);
        $totp = app(Totp::class);

        $mulai = $layanan->mulai($this->staf);
        $layanan->konfirmasi($this->staf->refresh(), $totp->kode($mulai['rahasia']));

        $this->rahasia = $mulai['rahasia'];
        $this->totp = $totp;

        $this->travel(31)->seconds();
    });

    it('tetap menantang TOTP sesudah IdP secara bawaan', function () {
        kembaliDariIdp(klaimStaf())->assertRedirect(route('dua-faktor.tantangan'));

        // Identitasnya diterima; yang masuk belum ada.
        $this->assertGuest('staff');

        expect(session('dua_faktor.staff_id'))->toBe($this->staf->id);

        $this->post('/masuk/dua-langkah', ['kode' => $this->totp->kode($this->rahasia)])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($this->staf, 'staff');
    });

    it('melewati TOTP hanya bila kampus menyatakan IdP sudah memenuhinya', function () {
        config(['idp.satisfies_2fa' => true]);

        kembaliDariIdp(klaimStaf())->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($this->staf, 'staff');

        expect(session('dua_faktor'))->toBeNull();
    });
});

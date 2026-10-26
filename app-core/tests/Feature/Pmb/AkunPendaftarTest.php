<?php

declare(strict_types=1);

use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Pmb\PmbAkun;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\Staff;
use App\Notifications\Pmb\AturUlangSandiPendaftar;
use App\Notifications\Pmb\VerifikasiSurelPendaftar;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;

/**
 * The `pendaftar` guard: an account that is not a campus identity.
 *
 * Most of what is pinned here is what the account must NOT be able to do —
 * reach the student portal, sign in on the campus page, survive a campus
 * sign-in in the same browser — because those are the failures that would
 * leak one portal into another.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

function akunPendaftarUji(array $atribut = []): PmbAkun
{
    return PmbAkun::create([
        'nama' => 'Calon Uji',
        'email' => fake()->unique()->safeEmail(),
        'password' => 'rahasia-123',
        'email_verified_at' => now(),
        ...$atribut,
    ]);
}

describe('daftar akun', function () {
    it('membuat akun, memasukkannya, dan mengirim tautan verifikasi', function () {
        Notification::fake();

        $this->post('/pmb/daftar', [
            'nama' => 'Sari Wulandari',
            'email' => 'Sari@Contoh.TEST',
            'password' => 'rahasia-123',
            'password_confirmation' => 'rahasia-123',
        ])->assertRedirect(route('pmb.verifikasi.pemberitahuan'));

        $akun = PmbAkun::firstWhere('email', 'sari@contoh.test');

        expect($akun)->not->toBeNull()
            ->and($akun->hasVerifiedEmail())->toBeFalse();

        $this->assertAuthenticatedAs($akun, 'pendaftar');
        Notification::assertSentTo($akun, VerifikasiSurelPendaftar::class);
    });

    it('menolak surel yang sudah punya akun pendaftar', function () {
        akunPendaftarUji(['email' => 'ada@contoh.test']);

        $this->post('/pmb/daftar', [
            'nama' => 'Orang Lain',
            'email' => 'ADA@contoh.test',
            'password' => 'rahasia-123',
            'password_confirmation' => 'rahasia-123',
        ])->assertSessionHasErrors(['email' => __('pmb.akun.surel_dipakai')]);
    });

    it('menolak konfirmasi kata sandi yang tidak sama', function () {
        $this->post('/pmb/daftar', [
            'nama' => 'Sari',
            'email' => 'sari@contoh.test',
            'password' => 'rahasia-123',
            'password_confirmation' => 'berbeda-123',
        ])->assertSessionHasErrors('password');
    });

    it('membatasi laju pendaftaran per IP', function () {
        config(['pmb.batas.daftar_per_jam' => 2]);

        foreach (range(1, 2) as $i) {
            $this->post('/pmb/daftar', [
                'nama' => "Calon {$i}",
                'email' => "calon{$i}@contoh.test",
                'password' => 'rahasia-123',
                'password_confirmation' => 'rahasia-123',
            ]);
            auth('pendaftar')->logout();
        }

        $this->post('/pmb/daftar', [
            'nama' => 'Calon 3',
            'email' => 'calon3@contoh.test',
            'password' => 'rahasia-123',
            'password_confirmation' => 'rahasia-123',
        ])->assertStatus(429);

        expect(PmbAkun::count())->toBe(2);
    });
});

describe('verifikasi surel', function () {
    it('menahan akun yang belum terverifikasi di luar portal', function () {
        $akun = akunPendaftarUji(['email_verified_at' => null]);

        $this->actingAs($akun, 'pendaftar')
            ->get('/pmb/portal')
            ->assertRedirect(route('pmb.verifikasi.pemberitahuan'));
    });

    it('memverifikasi lewat tautan bertanda tangan milik akun itu sendiri', function () {
        $akun = akunPendaftarUji(['email_verified_at' => null]);

        $url = URL::temporarySignedRoute('pmb.verifikasi.verify', now()->addHour(), [
            'akun' => $akun->uuid,
            'hash' => sha1($akun->email),
        ]);

        $this->actingAs($akun, 'pendaftar')->get($url)->assertRedirect(route('pmb.portal.index'));

        expect($akun->fresh()->hasVerifiedEmail())->toBeTrue();
    });

    it('menolak tautan akun lain dan tautan tanpa tanda tangan', function () {
        $akun = akunPendaftarUji(['email_verified_at' => null]);
        $lain = akunPendaftarUji(['email_verified_at' => null]);

        $milikLain = URL::temporarySignedRoute('pmb.verifikasi.verify', now()->addHour(), [
            'akun' => $lain->uuid,
            'hash' => sha1($lain->email),
        ]);

        $this->actingAs($akun, 'pendaftar')->get($milikLain)->assertForbidden();
        $this->actingAs($akun, 'pendaftar')
            ->get(route('pmb.verifikasi.verify', ['akun' => $akun->uuid, 'hash' => sha1($akun->email)]))
            ->assertForbidden();

        expect($akun->fresh()->hasVerifiedEmail())->toBeFalse()
            ->and($lain->fresh()->hasVerifiedEmail())->toBeFalse();
    });

    it('menautkan surel verifikasi ke rute PMB, bukan rute bawaan framework', function () {
        $akun = akunPendaftarUji(['email_verified_at' => null]);

        $surel = (new VerifikasiSurelPendaftar)->toMail($akun);

        expect($surel->actionUrl)->toContain('/pmb/verifikasi-surel/'.$akun->uuid.'/');
    });
});

describe('masuk dan keluar', function () {
    it('memasukkan pendaftar lewat halaman PMB', function () {
        $akun = akunPendaftarUji(['email' => 'calon@contoh.test']);

        $this->post('/pmb/masuk', ['email' => 'calon@contoh.test', 'password' => 'rahasia-123'])
            ->assertRedirect(route('pmb.portal.index'));

        $this->assertAuthenticatedAs($akun, 'pendaftar');
        expect($akun->fresh()->last_login_at)->not->toBeNull();
    });

    it('menolak kata sandi salah dan akun nonaktif dengan pesan yang sama', function () {
        akunPendaftarUji(['email' => 'aktif@contoh.test']);
        akunPendaftarUji(['email' => 'mati@contoh.test', 'is_active' => false]);

        $this->post('/pmb/masuk', ['email' => 'aktif@contoh.test', 'password' => 'salah'])
            ->assertSessionHasErrors(['email' => __('pmb.akun.gagal')]);
        $this->post('/pmb/masuk', ['email' => 'mati@contoh.test', 'password' => 'rahasia-123'])
            ->assertSessionHasErrors(['email' => __('pmb.akun.gagal')]);

        $this->assertGuest('pendaftar');
    });

    it('mengunci percobaan masuk sesudah batasnya', function () {
        config(['pmb.batas.masuk_percobaan' => 3]);
        akunPendaftarUji(['email' => 'calon@contoh.test']);

        foreach (range(1, 3) as $i) {
            $this->post('/pmb/masuk', ['email' => 'calon@contoh.test', 'password' => 'salah']);
        }

        // Even the right password is refused while locked out.
        $this->post('/pmb/masuk', ['email' => 'calon@contoh.test', 'password' => 'rahasia-123'])
            ->assertSessionHasErrors('email');

        $this->assertGuest('pendaftar');
    });

    it('mengeluarkan pendaftar', function () {
        $akun = akunPendaftarUji();

        $this->actingAs($akun, 'pendaftar')->post('/pmb/keluar')->assertRedirect(route('pmb.masuk'));

        $this->assertGuest('pendaftar');
    });

    it('mengarahkan tamu portal pendaftar ke halaman masuk PMB, bukan halaman masuk kampus', function () {
        $this->get('/pmb/portal')->assertRedirect(route('pmb.masuk'));
        $this->get('/mahasiswa')->assertRedirect(route('login'));
    });
});

describe('lupa kata sandi', function () {
    it('mengirim tautan atur ulang tanpa menaruh surel di URL', function () {
        Notification::fake();
        $akun = akunPendaftarUji(['email' => 'lupa@contoh.test']);

        $this->post('/pmb/lupa-sandi', ['email' => 'lupa@contoh.test'])
            ->assertSessionHas('sukses', __('pmb.sandi.terkirim'));

        Notification::assertSentTo($akun, AturUlangSandiPendaftar::class, function ($n) use ($akun) {
            $url = $n->toMail($akun)->actionUrl;

            return str_contains($url, '/pmb/atur-ulang-sandi/') && !str_contains($url, 'lupa%40') && !str_contains($url, 'lupa@');
        });
    });

    it('menjawab sama untuk surel yang tidak terdaftar', function () {
        Notification::fake();

        $this->post('/pmb/lupa-sandi', ['email' => 'tidakada@contoh.test'])
            ->assertSessionHas('sukses', __('pmb.sandi.terkirim'));

        Notification::assertNothingSent();
    });

    it('menyimpan kata sandi baru dengan token yang sah', function () {
        $akun = akunPendaftarUji(['email' => 'lupa@contoh.test', 'email_verified_at' => null]);
        $token = Password::broker('pendaftar')->createToken($akun);

        $this->post('/pmb/atur-ulang-sandi', [
            'token' => $token,
            'email' => 'lupa@contoh.test',
            'password' => 'sandi-baru-456',
            'password_confirmation' => 'sandi-baru-456',
        ])->assertRedirect(route('pmb.masuk'));

        $this->post('/pmb/masuk', ['email' => 'lupa@contoh.test', 'password' => 'sandi-baru-456'])
            ->assertRedirect(route('pmb.portal.index'));

        // Following the link proved the inbox.
        expect($akun->fresh()->hasVerifiedEmail())->toBeTrue();
    });

    it('menolak token palsu', function () {
        akunPendaftarUji(['email' => 'lupa@contoh.test']);

        $this->post('/pmb/atur-ulang-sandi', [
            'token' => 'palsu',
            'email' => 'lupa@contoh.test',
            'password' => 'sandi-baru-456',
            'password_confirmation' => 'sandi-baru-456',
        ])->assertSessionHasErrors(['email' => __('pmb.sandi.gagal')]);
    });

    it('menyimpan token di tabelnya sendiri, terpisah dari portal kampus', function () {
        $akun = akunPendaftarUji(['email' => 'bersama@contoh.test']);

        Password::broker('pendaftar')->createToken($akun);

        $this->assertDatabaseHas('pmb_password_reset_tokens', ['email' => 'bersama@contoh.test']);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'bersama@contoh.test']);
    });
});

describe('batas antar-guard', function () {
    it('tidak membiarkan sesi pendaftar membuka portal mahasiswa, dosen, atau staf', function () {
        $akun = akunPendaftarUji();

        $this->actingAs($akun, 'pendaftar');

        $this->get('/mahasiswa')->assertRedirect(route('login'));
        $this->get('/dosen')->assertRedirect(route('login'));
        $this->get('/admin')->assertRedirect(route('login'));
        $this->get('/admin/pmb')->assertRedirect(route('login'));
    });

    it('tidak membiarkan mahasiswa atau staf membuka portal pendaftar', function () {
        $mahasiswa = Mahasiswa::factory()->create();
        $staf = Staff::factory()->create();
        $staf->assignRole('super-admin');

        $this->actingAs($mahasiswa, 'mahasiswa')->get('/pmb/portal')->assertRedirect(route('pmb.masuk'));
        $this->actingAs($staf, 'staff')->get('/pmb/portal')->assertRedirect(route('pmb.masuk'));
    });

    it('tidak menerima kredensial pendaftar di halaman masuk kampus', function () {
        akunPendaftarUji(['email' => 'calon@contoh.test']);

        $this->post('/masuk', ['identitas' => 'calon@contoh.test', 'password' => 'rahasia-123'])
            ->assertSessionHasErrors('identitas');

        $this->assertGuest('pendaftar');
        $this->assertGuest('mahasiswa');
    });

    it('mengeluarkan sesi kampus saat pendaftar masuk di peramban yang sama', function () {
        $dosen = Dosen::factory()->create();
        akunPendaftarUji(['email' => 'calon@contoh.test']);

        $this->actingAs($dosen, 'dosen')
            ->post('/pmb/masuk', ['email' => 'calon@contoh.test', 'password' => 'rahasia-123']);

        $this->assertGuest('dosen');
        $this->assertAuthenticated('pendaftar');
    });

    it('mengeluarkan sesi pendaftar saat mahasiswa masuk di peramban yang sama', function () {
        $akun = akunPendaftarUji();
        Mahasiswa::factory()->create(['nim' => '2655201001', 'password' => 'rahasia']);

        $this->actingAs($akun, 'pendaftar')
            ->post('/masuk', ['identitas' => '2655201001', 'password' => 'rahasia']);

        $this->assertGuest('pendaftar');
        $this->assertAuthenticated('mahasiswa');
    });

    it('tidak dikenal penyedia SSO maupun guard API', function () {
        expect(array_keys((array) config('auth.providers')))->toContain('pendaftar')
            ->and(config('auth.guards.sso.provider'))->not->toBe('pendaftar')
            ->and(config('auth.guards.api.provider'))->not->toBe('pendaftar');
    });
});

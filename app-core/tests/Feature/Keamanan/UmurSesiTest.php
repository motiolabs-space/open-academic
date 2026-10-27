<?php

declare(strict_types=1);

use App\Models\Pmb\PmbAkun;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\Staff;
use App\Services\Sdm\KepegawaianService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * A session lives only as long as its account and its password.
 *
 * Each case signs in for real, changes the account behind the session's back,
 * then forgets the in-memory guards — which is what the next HTTP request in
 * production does — and asks whether the old session still works.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

function permintaanBaru(): void
{
    Auth::forgetGuards();
}

it('mengakhiri sesi staf yang dinonaktifkan sesudah masuk', function () {
    $staf = Staff::factory()->create(['email' => 'staf@kampus.test', 'password' => 'rahasia']);
    $staf->assignRole('baak');
    $admin = Staff::factory()->create();
    $admin->assignRole('super-admin');

    $this->post('/masuk', ['identitas' => 'staf@kampus.test', 'password' => 'rahasia']);
    $this->get('/notifikasi')->assertOk();

    app(KepegawaianService::class)->nonaktifkanStaff($staf);
    permintaanBaru();

    $this->get('/notifikasi')->assertRedirect(route('login'));
    $this->assertGuest('staff');

    // logout() cycled it: a remember cookie issued before is dead everywhere.
    expect($staf->fresh()->remember_token)->not->toBeNull();
});

it('mengakhiri sesi dosen yang dinonaktifkan sesudah masuk', function () {
    $dosen = Dosen::factory()->create(['email' => 'dosen@kampus.test', 'password' => 'rahasia']);
    $dosen->assignRole('dosen');

    $this->post('/masuk', ['identitas' => 'dosen@kampus.test', 'password' => 'rahasia']);
    $this->get('/notifikasi')->assertOk();

    $dosen->update(['is_active' => false]);
    permintaanBaru();

    $this->get('/notifikasi')->assertRedirect(route('login'));
    $this->assertGuest('dosen');
});

it('mengakhiri sesi lain saat administrator mengatur ulang kata sandi', function () {
    $staf = Staff::factory()->create(['email' => 'staf@kampus.test', 'password' => 'rahasia']);
    $staf->assignRole('baak');

    $this->post('/masuk', ['identitas' => 'staf@kampus.test', 'password' => 'rahasia']);
    $this->get('/notifikasi')->assertOk();

    $tokenIngat = $staf->fresh()->remember_token;

    app(KepegawaianService::class)->resetKataSandi($staf);
    permintaanBaru();

    $this->get('/notifikasi')->assertRedirect(route('login'));
    $this->assertGuest('staff');

    // A remember cookie carries the token, not the password: the token has
    // to change with the password or the cookie outlives the reset.
    expect($staf->fresh()->remember_token)->not->toBe($tokenIngat)->not->toBeNull();
});

it('mengakhiri sesi pendaftar lain saat kata sandinya diatur ulang', function () {
    // Pre-account takeover: somebody registers the victim's address first and
    // keeps that session open. When the real owner resets the password, the
    // squatter's session must die with the old password.
    $akun = PmbAkun::create([
        'nama' => 'Calon Uji',
        'email' => 'calon@example.test',
        'password' => 'rahasia-123',
        'email_verified_at' => now(),
    ]);

    $this->post('/pmb/masuk', ['email' => 'calon@example.test', 'password' => 'rahasia-123']);
    $this->get(route('pmb.portal.index'))->assertOk();

    $akun->forceFill(['password' => Hash::make('sandi-pemilik-asli')])->save();
    permintaanBaru();

    $this->get(route('pmb.portal.index'))->assertRedirect();
    $this->assertGuest('pendaftar');
});

it('tetap memasukkan pengguna yang mengganti kata sandinya sendiri', function () {
    $mahasiswa = \App\Models\Kemahasiswaan\Mahasiswa::factory()->create([
        'nim' => '2255201001',
        'password' => 'rahasia-lama',
    ]);

    $this->post('/masuk', ['identitas' => '2255201001', 'password' => 'rahasia-lama']);
    $this->get('/notifikasi')->assertOk();

    \App\Models\Akademik\TahunAkademik::factory()->berjalan()->create(['is_active' => true]);
    $this->post(route('mahasiswa.profil.kata-sandi'), [
        'kata_sandi_lama' => 'rahasia-lama',
        'kata_sandi' => 'rahasia-baru-123',
        'kata_sandi_confirmation' => 'rahasia-baru-123',
    ])->assertSessionHasNoErrors();
    permintaanBaru();

    // The device that made the change keeps its session; only the others go.
    $this->get('/notifikasi')->assertOk();
    $this->assertAuthenticatedAs($mahasiswa, 'mahasiswa');
});

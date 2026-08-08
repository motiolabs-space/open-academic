<?php

declare(strict_types=1);

use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\Staff;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Signing in against the *database* session driver.
 *
 * The rest of the suite runs on the array driver, which keeps nothing and
 * therefore proves nothing about the sessions table. That gap hid a real
 * breakage: when the authentication identifier became a UUID, `sessions.user_id`
 * was still an integer column, so every single sign-in failed with a 500 while
 * the whole test suite stayed green.
 *
 * `.env.example` ships SESSION_DRIVER=database, so this is what a real
 * installation does on every login.
 */
beforeEach(function () {
    config(['session.driver' => 'database']);

    $this->seed(RolePermissionSeeder::class);
});

it('menyimpan sesi mahasiswa ke basis data', function () {
    TahunAkademik::factory()->berjalan()->create(['is_active' => true]);

    $mahasiswa = Mahasiswa::factory()->create(['email' => 'uji@demo.test']);
    $mahasiswa->assignRole('mahasiswa');

    $this->post('/masuk', [
        'identitas' => 'uji@demo.test',
        'password' => 'password',
    ])->assertRedirect();

    $this->assertAuthenticatedAs($mahasiswa, 'mahasiswa');

    // Kolom sessions.user_id baru terisi pada request ke rute ber-guard: di
    // sanalah middleware auth: menetapkan guard default, sehingga handler sesi
    // tahu siapa pemiliknya. Itu juga persis request yang dulu gagal 500.
    $this->get('/mahasiswa')->assertOk();
});

it('menerima UUID pada kolom sessions.user_id', function () {
    // Diuji langsung di tingkat skema, bukan lewat request.
    //
    // Handler sesi Laravel baru mengisi user_id ketika guard default sudah
    // ditetapkan middleware auth:, dan pada lingkungan tes hal itu tidak
    // terjadi — sehingga tes berbasis request akan tetap hijau meski kolomnya
    // kembali bigint. Justru itulah cara regresi ini lolos pertama kali:
    // seluruh suite hijau sementara setiap login di server sungguhan gagal 500.
    $uuid = (string) Str::uuid();

    DB::table('sessions')->insert([
        'id' => 'uji-sesi',
        'user_id' => $uuid,
        'payload' => 'kosong',
        'last_activity' => time(),
    ]);

    expect(DB::table('sessions')->where('id', 'uji-sesi')->value('user_id'))->toBe($uuid);
});

it('menyimpan sesi dosen ke basis data', function () {
    $dosen = Dosen::factory()->create(['email' => 'dosen-uji@demo.test']);
    $dosen->assignRole('dosen');

    $this->post('/masuk', [
        'identitas' => 'dosen-uji@demo.test',
        'password' => 'password',
    ])->assertRedirect();

    $this->assertAuthenticatedAs($dosen, 'dosen');
});

it('menyimpan sesi staf ke basis data', function () {
    $staff = Staff::factory()->create(['email' => 'staf-uji@demo.test']);
    $staff->assignRole('super-admin');

    $this->post('/masuk', [
        'identitas' => 'staf-uji@demo.test',
        'password' => 'password',
    ])->assertRedirect();

    $this->assertAuthenticatedAs($staff, 'staff');
});

it('mengakhiri sesi guard lain saat masuk ke portal berbeda', function () {
    $dosen = Dosen::factory()->create(['email' => 'dua-peran@demo.test']);
    $dosen->assignRole('dosen');

    $mahasiswa = Mahasiswa::factory()->create(['email' => 'mhs-dua@demo.test']);
    $mahasiswa->assignRole('mahasiswa');

    $this->post('/masuk', ['identitas' => 'dua-peran@demo.test', 'password' => 'password']);
    $this->assertAuthenticatedAs($dosen, 'dosen');

    $this->post('/masuk', ['identitas' => 'mhs-dua@demo.test', 'password' => 'password']);

    $this->assertAuthenticatedAs($mahasiswa, 'mahasiswa');
    $this->assertGuest('dosen');
});

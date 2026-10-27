<?php

declare(strict_types=1);

use App\Enums\SemesterType;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Pmb\PmbAkun;
use App\Models\Sdm\Staff;
use App\Services\Auth\DuaFaktorService;
use App\Services\Auth\Totp;
use Database\Seeders\RolePermissionSeeder;

/**
 * Guessing a password or a code is limited per account and per address, not
 * only per (account, address) pair.
 *
 * With the pair alone, changing address bought a fresh allowance for the same
 * account, and one address could try one password against every NIM on campus.
 */
beforeEach(function () {
    // Small numbers keep the test fast: every failed portal sign-in is
    // time-boxed by the guard, six times over.
    config([
        'auth.batas_masuk.per_akun.maks' => 3,
        'auth.batas_masuk.per_alamat.maks' => 4,
    ]);
});

function dariAlamat($test, string $alamat)
{
    return $test->withServerVariables(['REMOTE_ADDR' => $alamat]);
}

it('membatasi tebakan kata sandi satu akun dari banyak alamat', function () {
    Staff::factory()->create(['email' => 'sasaran@kampus.test', 'password' => 'rahasia-benar']);

    foreach (range(1, 3) as $i) {
        dariAlamat($this, "10.0.0.{$i}")
            ->post('/masuk', ['identitas' => 'sasaran@kampus.test', 'password' => "tebakan-{$i}"]);
    }

    // Even the right password, from yet another address, waits now.
    dariAlamat($this, '10.0.1.1')
        ->post('/masuk', ['identitas' => 'sasaran@kampus.test', 'password' => 'rahasia-benar'])
        ->assertSessionHasErrors('identitas');

    $this->assertGuest('staff');
});

it('membatasi penyemprotan satu kata sandi ke banyak akun dari satu alamat', function () {
    Mahasiswa::factory()->create(['nim' => '2255201999', 'password' => 'rahasia-benar']);

    foreach (range(1, 4) as $i) {
        dariAlamat($this, '10.9.9.9')
            ->post('/masuk', ['identitas' => sprintf('22552010%02d', $i), 'password' => 'Kampus2026']);
    }

    dariAlamat($this, '10.9.9.9')
        ->post('/masuk', ['identitas' => '2255201999', 'password' => 'rahasia-benar'])
        ->assertSessionHasErrors('identitas');

    $this->assertGuest('mahasiswa');

    // Another address is unaffected: a NAT neighbour is not punished forever.
    dariAlamat($this, '10.9.9.10')
        ->post('/masuk', ['identitas' => '2255201999', 'password' => 'rahasia-benar'])
        ->assertSessionHasNoErrors();
});

it('membatasi tebakan kata sandi akun pendaftar dari banyak alamat', function () {
    PmbAkun::create([
        'nama' => 'Calon Uji',
        'email' => 'calon@example.test',
        'password' => 'rahasia-benar',
        'email_verified_at' => now(),
    ]);

    foreach (range(1, 3) as $i) {
        dariAlamat($this, "10.0.0.{$i}")
            ->post('/pmb/masuk', ['email' => 'calon@example.test', 'password' => "tebakan-{$i}"]);
    }

    dariAlamat($this, '10.0.1.1')
        ->post('/pmb/masuk', ['email' => 'calon@example.test', 'password' => 'rahasia-benar'])
        ->assertSessionHasErrors('email');

    $this->assertGuest('pendaftar');
});

it('membatasi tebakan kode dua langkah satu akun dari banyak alamat', function () {
    $this->seed(RolePermissionSeeder::class);
    TahunAkademik::factory()->term(2026, SemesterType::Ganjil)->berjalan()->aktif()->create();

    $staf = Staff::factory()->create();
    $staf->assignRole('super-admin');

    $layanan = app(DuaFaktorService::class);
    $totp = app(Totp::class);
    $rahasia = $layanan->mulai($staf)['rahasia'];
    $layanan->konfirmasi($staf->refresh(), $totp->kode($rahasia));
    $this->travel(31)->seconds();

    $menunggu = ['dua_faktor' => ['staff_id' => $staf->id, 'ingat' => false, 'sejak' => now()->timestamp]];

    // Two wrong codes from each of five addresses: never five from one.
    foreach (range(1, 10) as $i) {
        dariAlamat($this, '10.0.0.'.intdiv($i + 1, 2))
            ->withSession($menunggu)
            ->post('/masuk/dua-langkah', ['kode' => '000000']);
    }

    dariAlamat($this, '10.0.1.1')
        ->withSession($menunggu)
        ->post('/masuk/dua-langkah', ['kode' => $totp->kode($rahasia)])
        ->assertSessionHas('galat');

    $this->assertGuest('staff');
});

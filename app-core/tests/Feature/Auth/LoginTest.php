<?php

declare(strict_types=1);

use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\Staff;

it('mengarahkan tamu ke halaman masuk', function () {
    $this->get('/mahasiswa')->assertRedirect('/masuk');
    $this->get('/dosen')->assertRedirect('/masuk');
    $this->get('/admin')->assertRedirect('/masuk');
});

it('mengenali guard dari identitas yang diketik', function () {
    $mahasiswa = Mahasiswa::factory()->create(['nim' => '2255201001', 'password' => 'rahasia']);

    $this->post('/masuk', ['identitas' => '2255201001', 'password' => 'rahasia'])
        ->assertRedirect(route('mahasiswa.dashboard'));

    $this->assertAuthenticatedAs($mahasiswa, 'mahasiswa');
});

it('menerima surel selain nomor induk', function () {
    $dosen = Dosen::factory()->create(['email' => 'dosen@kampus.test', 'password' => 'rahasia']);

    $this->post('/masuk', ['identitas' => 'dosen@kampus.test', 'password' => 'rahasia'])
        ->assertRedirect(route('dosen.dashboard'));

    $this->assertAuthenticatedAs($dosen, 'dosen');
});

it('menolak kata sandi yang salah', function () {
    Staff::factory()->create(['email' => 'staf@kampus.test', 'password' => 'rahasia']);

    $this->post('/masuk', ['identitas' => 'staf@kampus.test', 'password' => 'salah'])
        ->assertSessionHasErrors('identitas');

    $this->assertGuest('staff');
});

it('menolak akun yang dinonaktifkan meski kata sandinya benar', function () {
    Staff::factory()->nonAktif()->create(['email' => 'mantan@kampus.test', 'password' => 'rahasia']);

    $this->post('/masuk', ['identitas' => 'mantan@kampus.test', 'password' => 'rahasia'])
        ->assertSessionHasErrors('identitas');

    $this->assertGuest('staff');
});

it('mencatat waktu masuk terakhir', function () {
    $staff = Staff::factory()->create(['email' => 'staf@kampus.test', 'password' => 'rahasia']);

    expect($staff->last_login_at)->toBeNull();

    $this->post('/masuk', ['identitas' => 'staf@kampus.test', 'password' => 'rahasia']);

    expect($staff->fresh()->last_login_at)->not->toBeNull();
});

it('mengeluarkan pengguna dan mengakhiri sesi', function () {
    $mahasiswa = Mahasiswa::factory()->create();

    $this->actingAs($mahasiswa, 'mahasiswa')
        ->post('/keluar')
        ->assertRedirect(route('login'));

    $this->assertGuest('mahasiswa');
});

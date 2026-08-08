<?php

declare(strict_types=1);

use App\Enums\SemesterType;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Staff;
use App\Support\Portal;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    Portal::lupakanTerm();
});

it('menahan portal ketika belum ada semester aktif', function () {
    $mahasiswa = Mahasiswa::factory()->create();
    $mahasiswa->assignRole('mahasiswa');

    $this->actingAs($mahasiswa, 'mahasiswa')
        ->get('/mahasiswa')
        ->assertStatus(503)
        ->assertSee('Belum Ada Semester Aktif');
});

it('memberi tahu staf cara memperbaikinya', function () {
    $staff = Staff::factory()->create();
    $staff->assignRole('super-admin');

    $this->actingAs($staff, 'staff')
        ->get('/admin')
        ->assertStatus(503)
        ->assertSee('Kalender Akademik');
});

it('tidak membebani mahasiswa dengan instruksi yang bukan wewenangnya', function () {
    $mahasiswa = Mahasiswa::factory()->create();
    $mahasiswa->assignRole('mahasiswa');

    $this->actingAs($mahasiswa, 'mahasiswa')
        ->get('/mahasiswa')
        ->assertDontSee('Kalender Akademik')
        ->assertSee('hubungi BAAK');
});

it('meloloskan portal begitu satu semester diaktifkan', function () {
    TahunAkademik::factory()->term(2026, SemesterType::Ganjil)->aktif()->create();
    Portal::lupakanTerm();

    $mahasiswa = Mahasiswa::factory()->create();
    $mahasiswa->assignRole('mahasiswa');

    $this->actingAs($mahasiswa, 'mahasiswa')
        ->get('/mahasiswa')
        ->assertOk();
});

it('tidak menghalangi halaman publik dan halaman masuk', function () {
    $this->get('/')->assertOk();
    $this->get('/masuk')->assertOk();
});

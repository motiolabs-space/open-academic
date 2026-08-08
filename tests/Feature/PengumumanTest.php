<?php

declare(strict_types=1);

use App\Models\Akademik\TahunAkademik;
use App\Models\Sdm\Staff;
use App\Models\System\Pengumuman;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    TahunAkademik::factory()->berjalan()->create(['is_active' => true]);

    $this->admin = Staff::factory()->create();
    $this->admin->assignRole('super-admin');

    $this->actingAs($this->admin, 'staff');
});

it('menyimpan draf yang belum terlihat siapa pun', function () {
    $this->post('/admin/pengumuman', [
        'judul' => 'Jadwal KRS Ganjil',
        'isi' => 'KRS dibuka 11 Agustus.',
        'target_roles' => ['mahasiswa'],
    ])->assertRedirect()->assertSessionHasNoErrors();

    $p = Pengumuman::firstOrFail();

    expect($p->published_at)->toBeNull()
        ->and(Pengumuman::terbit()->count())->toBe(0);
});

it('menjadwalkan terbit di masa mendatang', function () {
    // Inilah yang memungkinkan pengumuman ditulis Jumat dan muncul Senin.
    $this->post('/admin/pengumuman', [
        'judul' => 'Pengumuman Terjadwal',
        'isi' => 'Isi.',
        'target_roles' => ['mahasiswa'],
        'published_at' => now()->addWeek()->format('Y-m-d\TH:i'),
    ])->assertSessionHasNoErrors();

    expect(Pengumuman::terbit()->count())->toBe(0);

    $this->travel(8)->days();

    expect(Pengumuman::terbit()->count())->toBe(1);
});

it('mewajibkan setidaknya satu portal sasaran', function () {
    // Pengumuman tanpa sasaran tidak akan pernah terlihat siapa pun.
    $this->post('/admin/pengumuman', [
        'judul' => 'Tanpa Sasaran',
        'isi' => 'Isi.',
    ])->assertSessionHasErrors('target_roles');
});

it('menyaring menurut portal', function () {
    $this->post('/admin/pengumuman', [
        'judul' => 'Khusus Dosen',
        'isi' => 'Isi.',
        'target_roles' => ['dosen'],
        'published_at' => now()->format('Y-m-d\TH:i'),
    ]);

    expect(Pengumuman::terbit()->untuk('dosen')->count())->toBe(1)
        ->and(Pengumuman::terbit()->untuk('mahasiswa')->count())->toBe(0);
});

it('membuat slug unik untuk judul yang sama', function () {
    // "Jadwal KRS" dua semester berturut-turut itu lumrah, dan yang kedua tidak
    // boleh mengambil alih alamat yang pertama.
    foreach (range(1, 3) as $ignored) {
        $this->post('/admin/pengumuman', [
            'judul' => 'Jadwal KRS',
            'isi' => 'Isi.',
            'target_roles' => ['mahasiswa'],
        ]);
    }

    expect(Pengumuman::pluck('slug')->unique())->toHaveCount(3);
});

it('menerbitkan lalu menarik kembali', function () {
    $this->post('/admin/pengumuman', [
        'judul' => 'Uji Terbit', 'isi' => 'Isi.', 'target_roles' => ['mahasiswa'],
    ]);

    $p = Pengumuman::firstOrFail();

    $this->post("/admin/pengumuman/{$p->uuid}/terbitkan");
    expect($p->fresh()->published_at)->not->toBeNull();

    $this->post("/admin/pengumuman/{$p->uuid}/terbitkan");
    expect($p->fresh()->published_at)->toBeNull();
});

it('merender layar', function () {
    $this->post('/admin/pengumuman', [
        'judul' => 'Uji', 'isi' => 'Isi.', 'target_roles' => ['mahasiswa', 'dosen'],
    ]);

    $this->get('/admin/pengumuman')->assertOk()->assertSee('Uji');
});

it('menolak staf tanpa izin pengaturan', function () {
    $keuangan = Staff::factory()->create();
    $keuangan->assignRole('keuangan');

    $this->actingAs($keuangan, 'staff')->get('/admin/pengumuman')->assertForbidden();
});

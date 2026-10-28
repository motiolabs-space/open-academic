<?php

declare(strict_types=1);

use App\Enums\StudentStatus;
use App\Enums\UserRole;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\Staff;
use App\Models\System\Pengumuman;
use App\Support\Portal;
use Database\Seeders\RolePermissionSeeder;

/**
 * Announcements carry target_roles, but only the student dashboard rendered
 * them: one aimed at lecturers or staff was never seen by anybody. All three
 * portals now read Pengumuman::tampilUntuk().
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    TahunAkademik::factory()->berjalan()->create(['is_active' => true]);
    Portal::lupakanTerm();

    $prodi = Prodi::factory()->create();

    $this->mahasiswa = Mahasiswa::factory()->create(['prodi_id' => $prodi->id, 'status' => StudentStatus::Aktif]);
    $this->mahasiswa->assignRole('mahasiswa');

    $this->dosen = Dosen::factory()->create(['prodi_id' => $prodi->id]);
    $this->dosen->assignRole('dosen');

    // A staff member with no settings permission: reading announcements is
    // for everyone, managing them is not.
    $this->staf = Staff::factory()->create();
    $this->staf->assignRole('keuangan');
});

function pengumumanUji(string $judul, array $sasaran, array $atribut = []): Pengumuman
{
    return Pengumuman::create($atribut + [
        'judul' => $judul,
        'slug' => str($judul)->slug()->toString(),
        'ringkasan' => 'Ringkasan '.$judul,
        'isi' => 'Isi lengkap '.$judul,
        'target_roles' => $sasaran,
        'published_at' => now()->subDay(),
    ]);
}

it('menampilkan pengumuman untuk dosen di dasbor dan daftar dosen', function () {
    $untukDosen = pengumumanUji('Rapat Dosen Prodi', ['dosen']);
    pengumumanUji('Khusus Mahasiswa', ['mahasiswa']);

    $this->actingAs($this->dosen, 'dosen')
        ->get('/dosen')
        ->assertOk()
        ->assertSee('Rapat Dosen Prodi')
        ->assertDontSee('Khusus Mahasiswa');

    $this->get('/dosen/pengumuman')->assertOk()->assertSee('Rapat Dosen Prodi')->assertDontSee('Khusus Mahasiswa');
    $this->get("/dosen/pengumuman/{$untukDosen->uuid}")->assertOk()->assertSee('Isi lengkap Rapat Dosen Prodi');
});

it('menampilkan pengumuman untuk staf di dasbor dan papan staf tanpa izin pengaturan', function () {
    $untukStaf = pengumumanUji('Libur Bersama Pegawai', ['staff']);
    pengumumanUji('Rapat Dosen Prodi', ['dosen']);

    $this->actingAs($this->staf, 'staff')
        ->get('/admin')
        ->assertOk()
        ->assertSee('Libur Bersama Pegawai')
        ->assertDontSee('Rapat Dosen Prodi');

    $this->get('/admin/pengumuman/papan')->assertOk()->assertSee('Libur Bersama Pegawai');
    $this->get("/admin/pengumuman/papan/{$untukStaf->uuid}")->assertOk()->assertSee('Isi lengkap Libur Bersama Pegawai');

    // The management screen stays behind its permission.
    $this->get('/admin/pengumuman')->assertForbidden();
});

it('menyembunyikan draf, yang terjadwal, dan yang sudah berakhir dari ketiga portal', function () {
    pengumumanUji('Draf Semua', ['mahasiswa', 'dosen', 'staff'], ['published_at' => null]);
    pengumumanUji('Terjadwal Semua', ['mahasiswa', 'dosen', 'staff'], ['published_at' => now()->addDay()]);
    pengumumanUji('Berakhir Semua', ['mahasiswa', 'dosen', 'staff'], ['berakhir_at' => now()->subHour()]);
    pengumumanUji('Masih Berlaku', ['mahasiswa', 'dosen', 'staff'], ['berakhir_at' => now()->addWeek()]);

    foreach ([UserRole::Mahasiswa, UserRole::Dosen, UserRole::Staff] as $peran) {
        expect(Pengumuman::query()->tampilUntuk($peran)->pluck('judul')->all())->toBe(['Masih Berlaku']);
    }

    foreach ([[$this->mahasiswa, 'mahasiswa', '/mahasiswa'], [$this->dosen, 'dosen', '/dosen'], [$this->staf, 'staff', '/admin']] as [$aktor, $guard, $dasbor]) {
        $this->actingAs($aktor, $guard)
            ->get($dasbor)
            ->assertOk()
            ->assertSee('Masih Berlaku')
            ->assertDontSee('Draf Semua')
            ->assertDontSee('Terjadwal Semua')
            ->assertDontSee('Berakhir Semua');
    }
});

it('mendahulukan pengumuman yang disematkan', function () {
    pengumumanUji('Terbaru', ['dosen'], ['published_at' => now()->subHour()]);
    pengumumanUji('Disematkan Lama', ['dosen'], ['published_at' => now()->subMonth(), 'is_pinned' => true]);

    expect(Pengumuman::query()->tampilUntuk(UserRole::Dosen)->pluck('judul')->all())
        ->toBe(['Disematkan Lama', 'Terbaru']);
});

it('membalas 404 untuk pengumuman yang bukan sasaran portal pembaca', function () {
    $untukStaf = pengumumanUji('Internal Pegawai', ['staff']);
    $berakhir = pengumumanUji('Sudah Berakhir', ['mahasiswa'], ['berakhir_at' => now()->subMinute()]);

    $this->actingAs($this->mahasiswa, 'mahasiswa')->get("/mahasiswa/pengumuman/{$untukStaf->uuid}")->assertNotFound();
    $this->get("/mahasiswa/pengumuman/{$berakhir->uuid}")->assertNotFound();
});

it('menolak waktu berakhir sebelum waktu terbit saat menyimpan', function () {
    $admin = Staff::factory()->create();
    $admin->assignRole('super-admin');

    $this->actingAs($admin, 'staff')->post('/admin/pengumuman', [
        'judul' => 'Salah Urutan',
        'isi' => 'Isi.',
        'target_roles' => ['dosen'],
        'published_at' => now()->addDays(3)->format('Y-m-d\TH:i'),
        'berakhir_at' => now()->addDay()->format('Y-m-d\TH:i'),
    ])->assertSessionHasErrors('berakhir_at');

    $this->post('/admin/pengumuman', [
        'judul' => 'Urutan Benar',
        'isi' => 'Isi.',
        'target_roles' => ['dosen'],
        'published_at' => now()->format('Y-m-d\TH:i'),
        'berakhir_at' => now()->addWeek()->format('Y-m-d\TH:i'),
    ])->assertSessionHasNoErrors();

    expect(Pengumuman::where('judul', 'Urutan Benar')->value('berakhir_at'))->not->toBeNull();
});

<?php

declare(strict_types=1);

use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Staff;
use Database\Seeders\RolePermissionSeeder;

/**
 * Guards the behaviour that differs silently between database engines.
 *
 * These are not engine tests — the suite runs on SQLite. They pin the intent so
 * a future edit cannot reintroduce a raw `like`, whose case-sensitivity depends
 * on the engine and whose failure mode is a search box that quietly finds
 * nothing rather than an error anybody notices.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->prodi = Prodi::factory()->create();

    // Portal admin berada di balik gerbang semester aktif.
    TahunAkademik::factory()->berjalan()->create(['is_active' => true]);

    $this->staf = Staff::factory()->create();
    $this->staf->assignRole('super-admin');
});

describe('pencarian tidak peka huruf besar-kecil', function () {
    it('menemukan mahasiswa apa pun besar-kecil hurufnya', function () {
        Mahasiswa::factory()->create(['nama' => 'Budi Santoso', 'prodi_id' => $this->prodi->id]);

        foreach (['budi', 'BUDI', 'BuDi', 'santoso'] as $kata) {
            expect(Mahasiswa::cari($kata, ['nama', 'nim'])->count())
                ->toBe(1, "pencarian '{$kata}' seharusnya menemukan Budi Santoso");
        }
    });

    it('mencari lintas relasi', function () {
        $mahasiswa = Mahasiswa::factory()->create(['nama' => 'Siti Aminah', 'prodi_id' => $this->prodi->id]);

        expect(Mahasiswa::cari('siti', ['nama'])->count())->toBe(1)
            ->and(Mahasiswa::cari(mb_strtolower($this->prodi->nama), ['prodi.nama'])->count())
            ->toBeGreaterThanOrEqual(1);
    });

    it('mengembalikan kueri apa adanya saat kata kunci kosong', function () {
        Mahasiswa::factory()->count(3)->create(['prodi_id' => $this->prodi->id]);

        expect(Mahasiswa::cari(null, ['nama'])->count())->toBe(3)
            ->and(Mahasiswa::cari('   ', ['nama'])->count())->toBe(3);
    });

    it('tidak menelan filter di sekitarnya', function () {
        // OR yang tidak dibungkus mengubah "mahasiswa aktif bernama Budi"
        // menjadi "mahasiswa aktif, ATAU siapa pun bernama Budi".
        Mahasiswa::factory()->create([
            'nama' => 'Budi Aktif', 'prodi_id' => $this->prodi->id, 'status' => 'A',
        ]);
        Mahasiswa::factory()->create([
            'nama' => 'Budi Lulus', 'prodi_id' => $this->prodi->id, 'status' => 'L',
        ]);
        Mahasiswa::factory()->create([
            'nama' => 'Siti Aktif', 'prodi_id' => $this->prodi->id, 'status' => 'A',
        ]);

        $hasil = Mahasiswa::query()->where('status', 'A')->cari('budi', ['nama'])->get();

        expect($hasil)->toHaveCount(1)
            ->and($hasil->first()->nama)->toBe('Budi Aktif');
    });

    it('menemukan lewat layar Data Mahasiswa dengan huruf kecil', function () {
        Mahasiswa::factory()->create(['nama' => 'Ahmad Fauzi', 'prodi_id' => $this->prodi->id]);

        $this->actingAs($this->staf, 'staff')
            ->get('/admin/mahasiswa?cari=ahmad')
            ->assertOk()
            ->assertSee('Ahmad Fauzi');
    });
});

describe('tidak ada lagi like mentah', function () {
    it('tidak menyisakan operator like literal di kode aplikasi', function () {
        // Satu-satunya pengecualian yang sah adalah whereLike() milik Laravel,
        // yang memilih like/ilike sesuai driver.
        $berkas = collect(
            iterator_to_array(new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(app_path(), RecursiveDirectoryIterator::SKIP_DOTS),
            )),
        )->filter(fn ($f) => $f->isFile() && $f->getExtension() === 'php');

        $pelanggar = $berkas
            ->filter(fn ($f) => str_contains(file_get_contents($f->getPathname()), "'like'"))
            ->map(fn ($f) => str_replace(app_path().DIRECTORY_SEPARATOR, '', $f->getPathname()))
            ->values();

        expect($pelanggar->all())->toBe(
            [],
            "Pakai scopeCari() atau whereLike(), bukan where(..., 'like', ...): "
                .$pelanggar->implode(', '),
        );
    });
});

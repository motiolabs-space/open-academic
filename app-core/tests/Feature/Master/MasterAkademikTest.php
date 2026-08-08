<?php

declare(strict_types=1);

use App\Models\Akademik\Fakultas;
use App\Models\Akademik\Gedung;
use App\Models\Akademik\Kurikulum;
use App\Models\Akademik\MataKuliah;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\Ruang;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Staff;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->admin = Staff::factory()->create();
    $this->admin->assignRole('super-admin');

    $this->actingAs($this->admin, 'staff');
});

describe('gerbang instalasi baru', function () {
    it('dapat dibuka meski belum ada tahun akademik aktif', function () {
        // Ini alasan layar master berada di luar middleware term.active.
        // EnsureTermIsActive menjawab 503 bila tidak ada semester aktif — dan
        // layar inilah yang membuat semester pertama. Kalau ia ikut terjaga,
        // instalasi baru mustahil dipakai: operator disuruh menyiapkan
        // semester oleh halaman yang menolak dimuat sampai semester ada.
        expect(TahunAkademik::count())->toBe(0);

        $this->get('/admin/master')->assertOk();
        $this->get('/admin/master/prodi')->assertOk();

        // Sedangkan dasbor memang tetap terjaga.
        $this->get('/admin')->assertStatus(503);
    });

    it('membuat semester pertama lalu mengaktifkannya', function () {
        $this->post('/admin/master/tahun-akademik', [
            'tahun_mulai' => 2026,
            'semester' => '1',
            'tanggal_mulai' => '2026-09-01',
            'tanggal_selesai' => '2027-02-28',
        ])->assertRedirect();

        $term = TahunAkademik::firstOrFail();

        // Kode PDDIKTI dihitung, bukan diketik — salah ketik satu digit
        // memindahkan seluruh pelaporan satu semester ke semester lain.
        expect($term->kode)->toBe('20261')
            ->and($term->nama)->toBe('2026/2027 Ganjil')
            ->and($term->is_active)->toBeFalse();

        $this->post("/admin/master/tahun-akademik/{$term->uuid}/aktifkan")->assertRedirect();

        expect($term->fresh()->is_active)->toBeTrue();

        // Setelah ada semester aktif, portal terbuka.
        $this->get('/admin')->assertOk();
    });
});

it('merender keenam layar master dengan data nyata', function (string $url) {
    // Setiap tab punya Blade sendiri; tanpa ini galat template pada empat tab
    // yang tidak diuji alur bisnisnya baru ketahuan di produksi.
    $kurikulum = Kurikulum::factory()->create();
    MataKuliah::factory()->create(['prodi_id' => $kurikulum->prodi_id]);
    Mahasiswa::factory()->create(['prodi_id' => $kurikulum->prodi_id]);

    // Tidak ada factory untuk gedung/ruang; dibuat langsung.
    $gedung = Gedung::create(['kode' => 'A', 'nama' => 'Gedung A']);
    Ruang::create([
        'gedung_id' => $gedung->id,
        'kode' => 'A-101',
        'nama' => 'Ruang 101',
        'kapasitas' => 40,
        'jenis' => 'kelas',
        'is_active' => true,
    ]);

    $this->get($url)->assertOk();
})->with([
    '/admin/master/tahun-akademik',
    '/admin/master/fakultas',
    '/admin/master/prodi',
    '/admin/master/kurikulum',
    '/admin/master/mata-kuliah',
    '/admin/master/ruang',
]);

it('merender penyusun mata kuliah kurikulum', function () {
    $kurikulum = Kurikulum::factory()->create();
    $mk = MataKuliah::factory()->create(['prodi_id' => $kurikulum->prodi_id]);
    $kurikulum->mataKuliah()->attach($mk->id, ['semester' => 1, 'jenis' => 'wajib']);

    $this->get("/admin/master/kurikulum?kurikulum={$kurikulum->id}")
        ->assertOk()
        ->assertSee($mk->kode);
});

describe('tahun akademik', function () {
    it('hanya mengizinkan satu semester aktif', function () {
        $lama = TahunAkademik::factory()->create(['is_active' => true]);
        $baru = TahunAkademik::factory()->create(['kode' => '20262', 'is_active' => false]);

        $this->post("/admin/master/tahun-akademik/{$baru->uuid}/aktifkan")->assertRedirect();

        expect($baru->fresh()->is_active)->toBeTrue()
            ->and($lama->fresh()->is_active)->toBeFalse()
            ->and(TahunAkademik::where('is_active', true)->count())->toBe(1);
    });

    it('menolak masa KRS yang ditutup sebelum dibuka', function () {
        $this->post('/admin/master/tahun-akademik', [
            'tahun_mulai' => 2026,
            'semester' => '1',
            'tanggal_mulai' => '2026-09-01',
            'tanggal_selesai' => '2027-02-28',
            'krs_mulai' => '2026-09-10',
            'krs_selesai' => '2026-09-01',
        ])->assertSessionHasErrors('krs_selesai');
    });

    it('menolak gerbang kalender di luar rentang semester', function () {
        $this->post('/admin/master/tahun-akademik', [
            'tahun_mulai' => 2026,
            'semester' => '1',
            'tanggal_mulai' => '2026-09-01',
            'tanggal_selesai' => '2027-02-28',
            'krs_mulai' => '2026-09-05',
            'krs_selesai' => '2027-08-01',
        ])->assertSessionHasErrors('krs_selesai');
    });

    it('menolak mengunci semester yang sedang berjalan', function () {
        $term = TahunAkademik::factory()->create(['is_active' => true]);

        $this->post("/admin/master/tahun-akademik/{$term->uuid}/kunci")->assertRedirect();

        expect($term->fresh()->is_locked)->toBeFalse();
    });

    it('mewajibkan alasan saat membuka kunci', function () {
        $term = TahunAkademik::factory()->create(['is_locked' => true]);

        $this->post("/admin/master/tahun-akademik/{$term->uuid}/buka-kunci", ['alasan' => ''])
            ->assertSessionHasErrors('alasan');

        expect($term->fresh()->is_locked)->toBeTrue();
    });
});

describe('prasyarat mata kuliah', function () {
    it('menolak prasyarat yang membentuk lingkaran', function () {
        $prodi = Prodi::factory()->create();
        $a = MataKuliah::factory()->create(['prodi_id' => $prodi->id]);
        $b = MataKuliah::factory()->create(['prodi_id' => $prodi->id]);

        $this->post("/admin/master/mata-kuliah/{$a->uuid}/prasyarat", [
            'prasyarat_id' => $b->id,
            'jenis' => 'prasyarat',
        ])->assertRedirect();

        expect($a->prasyarat()->count())->toBe(1);

        // B kini menunggu A, sementara A sudah menunggu B. Keduanya tidak akan
        // pernah bisa diambil siapa pun.
        $this->post("/admin/master/mata-kuliah/{$b->uuid}/prasyarat", [
            'prasyarat_id' => $a->id,
            'jenis' => 'prasyarat',
        ])->assertSessionHas('galat');

        expect($b->fresh()->prasyarat()->count())->toBe(0);
    });

    it('menolak mata kuliah menjadi prasyarat bagi dirinya sendiri', function () {
        $mk = MataKuliah::factory()->create();

        $this->post("/admin/master/mata-kuliah/{$mk->uuid}/prasyarat", [
            'prasyarat_id' => $mk->id,
            'jenis' => 'prasyarat',
        ])->assertSessionHas('galat');

        expect($mk->prasyarat()->count())->toBe(0);
    });
});

describe('perlindungan penghapusan', function () {
    it('menolak menghapus prodi yang masih punya mahasiswa', function () {
        $mahasiswa = Mahasiswa::factory()->create();

        $this->delete("/admin/master/prodi/{$mahasiswa->prodi->uuid}")->assertSessionHas('galat');

        expect(Prodi::whereKey($mahasiswa->prodi_id)->exists())->toBeTrue();
    });

    it('menolak menghapus fakultas yang masih menaungi prodi', function () {
        $prodi = Prodi::factory()->create();

        $this->delete("/admin/master/fakultas/{$prodi->fakultas->uuid}")->assertSessionHas('galat');

        expect(Fakultas::whereKey($prodi->fakultas_id)->exists())->toBeTrue();
    });

    it('menolak mengaktifkan kurikulum tanpa mata kuliah', function () {
        $kurikulum = Kurikulum::factory()->create(['is_active' => false]);

        $this->post("/admin/master/kurikulum/{$kurikulum->uuid}/aktifkan")->assertSessionHas('galat');

        expect($kurikulum->fresh()->is_active)->toBeFalse();
    });
});

describe('otorisasi', function () {
    it('menolak staf tanpa izin master', function () {
        $keuangan = Staff::factory()->create();
        $keuangan->assignRole('keuangan');

        $this->actingAs($keuangan, 'staff')->get('/admin/master')->assertForbidden();
    });
});

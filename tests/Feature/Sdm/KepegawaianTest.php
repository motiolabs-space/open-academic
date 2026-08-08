<?php

declare(strict_types=1);

use App\Models\Akademik\KelasKuliah;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\Staff;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    TahunAkademik::factory()->berjalan()->create(['is_active' => true]);

    $this->admin = Staff::factory()->create();
    $this->admin->assignRole('super-admin');

    $this->actingAs($this->admin, 'staff');
});

describe('dosen', function () {
    it('membuat dosen dengan kata sandi acak yang ditampilkan sekali', function () {
        $this->post('/admin/dosen', [
            'nama' => 'Budi Santoso',
            'email' => 'budi@kampus.test',
            'nidn' => '0011223344',
            'status_kepegawaian' => 'tetap',
        ])->assertRedirect()->assertSessionHas('kata_sandi_baru');

        $dosen = Dosen::where('email', 'budi@kampus.test')->firstOrFail();

        expect($dosen->hasRole('dosen'))->toBeTrue()
            ->and($dosen->is_active)->toBeTrue();

        // Kata sandi yang dibuat administrator adalah kata sandi yang masih
        // diketahui administrator. Karena itu dibangkitkan, bukan diketik.
        $kataSandi = session('kata_sandi_baru')['kata_sandi'];
        expect(Hash::check($kataSandi, $dosen->password))->toBeTrue();
    });

    it('mewajibkan instansi asal untuk dosen praktisi', function () {
        $this->post('/admin/dosen', [
            'nama' => 'Praktisi Industri',
            'email' => 'praktisi@kampus.test',
            'status_kepegawaian' => 'luar_biasa',
            'is_praktisi' => '1',
            'praktisi_instansi' => '',
        ])->assertSessionHasErrors('praktisi_instansi');
    });

    it('menerima dosen praktisi tanpa NIDN', function () {
        // Praktisi industri lazimnya tidak punya NIDN, dan validator pra-kirim
        // Feeder-lah yang menolak mendorongnya — bukan formulir ini.
        $this->post('/admin/dosen', [
            'nama' => 'Praktisi Industri',
            'email' => 'praktisi2@kampus.test',
            'status_kepegawaian' => 'luar_biasa',
            'is_praktisi' => '1',
            'praktisi_instansi' => 'PT Nusantara Digital',
        ])->assertSessionHasNoErrors();

        expect(Dosen::where('email', 'praktisi2@kampus.test')->firstOrFail()->nidn)->toBeNull();
    });

    it('menolak menonaktifkan dosen yang masih mengampu kelas semester berjalan', function () {
        $dosen = Dosen::factory()->create();
        $kelas = KelasKuliah::factory()->create(['tahun_akademik_id' => TahunAkademik::aktif()->id]);
        $kelas->dosen()->attach($dosen->id, ['peran' => 'pengampu']);

        $this->post("/admin/dosen/{$dosen->uuid}/nonaktifkan")
            ->assertRedirect()
            ->assertSessionHas('galat');

        // Kalau lolos, kelas itu tak punya siapa pun yang bisa memasukkan
        // nilainya, dan tidak ada yang sadar sampai tenggat.
        expect($dosen->fresh()->is_active)->toBeTrue();
    });

    it('menolak menonaktifkan dosen wali mahasiswa aktif', function () {
        $dosen = Dosen::factory()->create();
        Mahasiswa::factory()->create(['dosen_wali_id' => $dosen->id, 'status' => 'A']);

        $this->post("/admin/dosen/{$dosen->uuid}/nonaktifkan")->assertSessionHas('galat');

        expect($dosen->fresh()->is_active)->toBeTrue();
    });

    it('mengizinkan menonaktifkan dosen tanpa tanggung jawab berjalan', function () {
        $dosen = Dosen::factory()->create();

        $this->post("/admin/dosen/{$dosen->uuid}/nonaktifkan")->assertSessionHas('sukses');

        expect($dosen->fresh()->is_active)->toBeFalse();
    });

    it('mengganti kata sandi lama saat direset', function () {
        $dosen = Dosen::factory()->create();
        $lama = $dosen->password;

        $this->post("/admin/dosen/{$dosen->uuid}/reset-sandi")->assertSessionHas('kata_sandi_baru');

        expect($dosen->fresh()->password)->not->toBe($lama);
    });
});

describe('staf', function () {
    it('menolak menonaktifkan super admin terakhir', function () {
        // Mengunci seluruh administrator dari sistem tidak bisa dipulihkan
        // lewat antarmuka — butuh akses basis data.
        expect(Staff::whereHas('roles', fn ($q) => $q->where('name', 'super-admin'))->count())->toBe(1);

        $this->post("/admin/staf/{$this->admin->uuid}/nonaktifkan")->assertSessionHas('galat');

        expect($this->admin->fresh()->is_active)->toBeTrue();
    });

    it('mengizinkan menonaktifkan super admin bila masih ada yang lain', function () {
        $cadangan = Staff::factory()->create();
        $cadangan->assignRole('super-admin');

        $this->post("/admin/staf/{$this->admin->uuid}/nonaktifkan")->assertSessionHas('sukses');

        expect($this->admin->fresh()->is_active)->toBeFalse();
    });

    it('mencatat perubahan peran pada jejak audit', function () {
        $staf = Staff::factory()->create();
        $staf->assignRole('baak');

        $this->put("/admin/staf/{$staf->uuid}", [
            'nama' => $staf->nama,
            'email' => $staf->email,
            'peran' => 'keuangan',
        ])->assertRedirect();

        expect($staf->fresh()->hasRole('keuangan'))->toBeTrue()
            ->and($staf->fresh()->hasRole('baak'))->toBeFalse();
    });
});

describe('otorisasi', function () {
    it('menolak staf tanpa izin kepegawaian', function () {
        $keuangan = Staff::factory()->create();
        $keuangan->assignRole('keuangan');

        $this->actingAs($keuangan, 'staff')->get('/admin/staf')->assertForbidden();
    });

    it('merender layar dosen dan staf', function (string $url) {
        Dosen::factory()->count(3)->create();

        $this->get($url)->assertOk();
    })->with(['/admin/dosen', '/admin/staf']);
});

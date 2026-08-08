<?php

declare(strict_types=1);

use App\Enums\JenisSurat;
use App\Enums\SemesterType;
use App\Enums\StatusSurat;
use App\Enums\StudentStatus;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Kemahasiswaan\StatusMahasiswa;
use App\Models\Sdm\Staff;
use App\Services\Surat\SuratService;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->term = TahunAkademik::factory()->term(2026, SemesterType::Ganjil)->aktif()->create();
    $this->prodi = Prodi::factory()->create();

    $this->staf = Staff::factory()->create();
    $this->staf->assignRole('baak');

    $this->mahasiswa = mahasiswaLayarSurat();
    $this->surat = app(SuratService::class);
});

function mahasiswaLayarSurat(): Mahasiswa
{
    $mahasiswa = Mahasiswa::factory()->create([
        'prodi_id' => test()->prodi->id,
        'status' => StudentStatus::Aktif,
    ]);

    $mahasiswa->assignRole('mahasiswa');

    StatusMahasiswa::create([
        'mahasiswa_id' => $mahasiswa->id,
        'tahun_akademik_id' => test()->term->id,
        'semester_ke' => 5,
        'status' => StudentStatus::Aktif,
        'sks_semester' => 20,
        'sks_kumulatif' => 100,
        'ips' => 3.4,
        'ipk' => 3.4,
    ]);

    return $mahasiswa->fresh();
}

describe('layar mahasiswa', function () {
    it('merender daftar dan pilihan surat', function () {
        $this->actingAs($this->mahasiswa, 'mahasiswa')
            ->get(route('mahasiswa.surat'))
            ->assertOk()
            ->assertSee('Surat Keterangan Aktif Kuliah')
            ->assertSee('Terbit langsung');
    });

    it('menerbitkan surat aktif kuliah lewat layar', function () {
        $this->actingAs($this->mahasiswa, 'mahasiswa')
            ->post(route('mahasiswa.surat.ajukan'), ['jenis' => JenisSurat::AktifKuliah->value])
            ->assertRedirect();

        expect($this->mahasiswa->surat()->first()->status)->toBe(StatusSurat::Diterbitkan);
    });

    it('menjelaskan alasan sebuah surat belum dapat diajukan', function () {
        // Menampilkan alasannya di layar adalah separuh dari gunanya modul ini:
        // yang tersisa dari antrean loket adalah perjalanan untuk bertanya.
        $this->actingAs($this->mahasiswa, 'mahasiswa')
            ->get(route('mahasiswa.surat'))
            ->assertOk()
            ->assertSee('kelulusan belum ditetapkan');
    });

    it('menolak mahasiswa mengunduh surat milik mahasiswa lain', function () {
        $korban = mahasiswaLayarSurat();
        $surat = $this->surat->ajukan($korban, JenisSurat::AktifKuliah);

        $this->actingAs($this->mahasiswa, 'mahasiswa')
            ->get(route('mahasiswa.surat.unduh', $surat))
            ->assertForbidden();
    });

    it('mengizinkan pemiliknya mengunduh surat yang sama', function () {
        // Pasangan tes di atas: rute dan binding identik, satu 403 satu 200.
        // Tanpa ini, tes penolakan bisa lulus karena salah binding.
        $surat = $this->surat->ajukan($this->mahasiswa, JenisSurat::AktifKuliah);

        $this->actingAs($this->mahasiswa, 'mahasiswa')
            ->get(route('mahasiswa.surat.unduh', $surat))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    });

    it('menolak mahasiswa mengajukan SKPI lewat layar', function () {
        $this->actingAs($this->mahasiswa, 'mahasiswa')
            ->post(route('mahasiswa.surat.ajukan'), ['jenis' => JenisSurat::Skpi->value])
            ->assertSessionHasErrors('jenis');
    });
});

describe('layar admin', function () {
    it('merender antrean', function () {
        $this->surat->ajukan($this->mahasiswa, JenisSurat::Pengantar, 'Penelitian lapangan.');

        $this->actingAs($this->staf, 'staff')
            ->get(route('admin.surat'))
            ->assertOk()
            ->assertSee('Penelitian lapangan.');
    });

    it('menerbitkan lewat layar', function () {
        $surat = $this->surat->ajukan($this->mahasiswa, JenisSurat::Pengantar, 'Magang.');

        $this->actingAs($this->staf, 'staff')
            ->post(route('admin.surat.terbitkan', $surat))
            ->assertRedirect();

        expect($surat->fresh()->status)->toBe(StatusSurat::Diterbitkan)
            ->and($surat->fresh()->diterbitkan_by_staff_id)->toBe($this->staf->id);
    });

    it('mewajibkan alasan saat menolak lewat layar', function () {
        $surat = $this->surat->ajukan($this->mahasiswa, JenisSurat::Pengantar, 'Magang.');

        $this->actingAs($this->staf, 'staff')
            ->post(route('admin.surat.tolak', $surat), [])
            ->assertSessionHasErrors('alasan');
    });

    it('menolak staf tanpa izin surat', function () {
        $surat = $this->surat->ajukan($this->mahasiswa, JenisSurat::Pengantar, 'Magang.');

        $keuangan = Staff::factory()->create();
        $keuangan->assignRole('keuangan');

        $this->actingAs($keuangan, 'staff')->get(route('admin.surat'))->assertForbidden();
        $this->actingAs($keuangan, 'staff')->post(route('admin.surat.terbitkan', $surat))->assertForbidden();
    });
});

describe('master capaian pembelajaran', function () {
    it('merender layar dan memperingatkan saat prodi belum punya capaian', function () {
        // Tanpa CPL, bagian tengah setiap SKPI tercetak sebagai "belum
        // dicatatkan" — bagian yang justru paling dibaca pihak luar negeri.
        $this->actingAs($this->staf, 'staff')
            ->get(route('admin.master.cpl'))
            ->assertOk()
            ->assertSee('belum memiliki capaian pembelajaran');
    });

    it('menambahkan capaian lewat layar', function () {
        $this->actingAs($this->staf, 'staff')
            ->post(route('admin.master.cpl.store'), [
                'prodi_id' => $this->prodi->id,
                'kode' => 'CPL-01',
                'kategori' => 'sikap',
                'deskripsi' => 'Menjunjung tinggi nilai kemanusiaan.',
                'deskripsi_en' => 'Upholds human values.',
            ])
            ->assertRedirect();

        expect($this->prodi->cpl()->count())->toBe(1);
    });

    it('menolak kode ganda pada prodi yang sama', function () {
        foreach ([1, 2] as $i) {
            $respons = $this->actingAs($this->staf, 'staff')
                ->post(route('admin.master.cpl.store'), [
                    'prodi_id' => $this->prodi->id,
                    'kode' => 'CPL-01',
                    'kategori' => 'sikap',
                    'deskripsi' => 'Deskripsi.',
                ]);
        }

        $respons->assertSessionHasErrors('kode');
        expect($this->prodi->cpl()->count())->toBe(1);
    });
});

describe('halaman verifikasi publik', function () {
    it('terbuka tanpa autentikasi', function () {
        // Yang memeriksa adalah petugas bank atau staf kedutaan. Meminta mereka
        // membuat akun berarti tidak akan ada yang pernah memverifikasi.
        $this->get(route('verifikasi.formulir'))->assertOk();
    });

    it('menampilkan surat yang asli lewat uuid', function () {
        $surat = $this->surat->ajukan($this->mahasiswa, JenisSurat::AktifKuliah);

        $this->get(route('verifikasi.surat', $surat->uuid))
            ->assertOk()
            ->assertSee('terdaftar dan asli')
            ->assertSee($surat->nomor)
            ->assertSee($this->mahasiswa->nama);
    });

    it('tidak membocorkan data pribadi lain', function () {
        $this->mahasiswa->update(['alamat' => 'Jalan Rahasia Nomor 7']);
        $surat = $this->surat->ajukan($this->mahasiswa->fresh(), JenisSurat::AktifKuliah);

        $this->get(route('verifikasi.surat', $surat->uuid))
            ->assertOk()
            ->assertDontSee('Jalan Rahasia Nomor 7');
    });

    it('menandai surat yang dicabut sebagai dicabut, bukan tidak ditemukan', function () {
        $surat = $this->surat->ajukan($this->mahasiswa, JenisSurat::AktifKuliah);
        $this->surat->cabut($surat, $this->staf, 'Data keliru.');

        $this->get(route('verifikasi.surat', $surat->uuid))
            ->assertOk()
            ->assertSee('Dicabut')
            ->assertSee('telah dicabut oleh penerbitnya');
    });

    it('menemukan lewat nomor dan NIM sekaligus', function () {
        $surat = $this->surat->ajukan($this->mahasiswa, JenisSurat::AktifKuliah);

        $this->post(route('verifikasi.cari'), [
            'nomor' => $surat->nomor,
            'nim' => $this->mahasiswa->nim,
        ])->assertOk()->assertSee('terdaftar dan asli');
    });

    it('menolak nomor benar dengan NIM keliru', function () {
        $surat = $this->surat->ajukan($this->mahasiswa, JenisSurat::AktifKuliah);

        $this->post(route('verifikasi.cari'), [
            'nomor' => $surat->nomor,
            'nim' => '999999999',
        ])->assertOk()->assertSee('tidak ditemukan');
    });

    it('membatasi laju pencarian manual', function () {
        // Deret nomor dapat ditebak, dan jawabannya otoritatif — itulah yang
        // membuat menebak layak dilakukan seseorang.
        config(['surat.verifikasi.batas_per_menit' => 3]);

        foreach (range(1, 3) as $i) {
            $this->post(route('verifikasi.cari'), ['nomor' => "000{$i}/X", 'nim' => '123'])
                ->assertOk();
        }

        $this->post(route('verifikasi.cari'), ['nomor' => '0004/X', 'nim' => '123'])
            ->assertStatus(429);
    });

    it('menjawab 404 saat verifikasi dimatikan kampus', function () {
        config(['surat.verifikasi.aktif' => false]);

        $this->get(route('verifikasi.formulir'))->assertNotFound();
    });
});

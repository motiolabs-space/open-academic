<?php

declare(strict_types=1);

use App\Enums\SemesterType;
use App\Models\Akademik\TahunAkademik;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\Staff;
use App\Services\Sdm\EksporSister;
use Database\Seeders\RolePermissionSeeder;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Ekspor per kelompok data SISTER, selagi belum ada tujuan pengirimannya.
 *
 * Yang diuji terutama bukan isi berkasnya melainkan apa yang TIDAK diekspor,
 * dan apakah alasannya sampai ke layar. Berkas kosong dan kampus tanpa data
 * terlihat sama persis, dan hanya satu di antaranya yang benar.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    TahunAkademik::factory()->term(2026, SemesterType::Ganjil)->berjalan()->aktif()->create();

    $this->staf = Staff::factory()->create();
    $this->staf->assignRole('super-admin');

    $this->sister = app(EksporSister::class);
});

/**
 * Isi sebuah unduhan yang dialirkan.
 *
 * StreamedResponse menulis ke keluaran alih-alih menyimpan badan respons, jadi
 * isinya hanya dapat diperiksa dengan menangkap buffer keluarannya.
 */
function tangkapUnduhan(StreamedResponse $respons): string
{
    ob_start();
    $respons->sendContent();

    return (string) ob_get_clean();
}

describe('katalog', function () {
    it('menyebut kelompok yang belum punya jalur pengisian, bukan menyembunyikannya', function () {
        /*
         * Empat kelompok punya tabel dan relasi tanpa satu pun layar untuk
         * mengisinya. Menghilangkannya dari daftar akan membuatnya terlihat
         * seperti kelompok yang memang tidak ditanyakan SISTER.
         */
        $katalog = $this->sister->katalog();

        expect($katalog)->toHaveKeys([
            'penghargaan_sanksi', 'bahasa', 'organisasi', 'keluarga',
        ]);

        foreach (['penghargaan_sanksi', 'bahasa', 'organisasi'] as $kunci) {
            expect($katalog[$kunci]['tersedia'])->toBeFalse()
                ->and($katalog[$kunci]['alasan'])->toContain('belum ada layar');
        }
    });

    it('menandai keluarga sebagai keputusan, bukan kekurangan', function () {
        // Tabelnya sama-sama kosong, tetapi alasannya berbeda — dan alasan yang
        // berbeda menuntut penanganan yang berbeda.
        $keluarga = $this->sister->katalog()['keluarga'];

        expect($keluarga['tersedia'])->toBeFalse()
            ->and($keluarga['alasan'])->toContain('Sengaja tidak diekspor');
    });

    it('menyertai pangkat dan mutasi dengan catatan, karena nol barisnya menyesatkan', function () {
        /*
         * Ekspornya jalan dan RiwayatKepegawaianService bisa menulisnya, tetapi
         * tak ada satu pun layar yang memanggilnya. Kampus membaca "0 baris"
         * lalu menyimpulkan tidak ada kenaikan pangkat tercatat — angkanya
         * benar, kesimpulannya salah.
         */
        $katalog = $this->sister->katalog();

        foreach (['pangkat', 'mutasi'] as $kunci) {
            expect($katalog[$kunci]['tersedia'])->toBeTrue()
                ->and($katalog[$kunci]['catatan'])->toContain('belum ada layar');
        }

        // Kelompok yang jalur pengisiannya utuh tidak membawa catatan apa pun.
        expect($katalog['biodata']['catatan'])->toBeNull();
    });

    it('menghitung baris untuk kelompok yang tersedia', function () {
        Dosen::factory()->count(3)->create();

        expect($this->sister->katalog()['biodata']['baris'])->toBe(3);
    });
});

describe('berkas', function () {
    it('mengekspor biodata tanpa NIK maupun alamat rumah', function () {
        /*
         * Aturan yang sama dengan portofolio JSON: berkas ini beredar lewat
         * surel ke bagian fakultas, dan itu saluran yang berbeda dari
         * pengiriman ke kementerian. Muatan yang aman di satu saluran dan tidak
         * di saluran lain pada akhirnya bocor lewat yang ceroboh.
         */
        Dosen::factory()->create(['nidn' => '0412058901', 'nik' => '3273010101900001']);

        $isi = tangkapUnduhan($this->sister->csv('biodata'));

        expect($isi)->toContain('0412058901')
            ->and($isi)->not->toContain('3273010101900001')
            ->and($isi)->not->toContain('NIK');
    });

    it('menolak mengekspor kelompok yang tidak dapat direkam', function () {
        expect(fn () => $this->sister->csv('bahasa'))
            ->toThrow(RuntimeException::class, 'tidak dapat diekspor');
    });

    it('menolak kelompok yang tidak dikenal', function () {
        expect(fn () => $this->sister->csv('entah'))
            ->toThrow(RuntimeException::class, 'tidak dikenal');
    });
});

describe('layar dan rute', function () {
    it('menampilkan seluruh kelompok beserta alasan yang belum tersedia', function () {
        $this->actingAs($this->staf, 'staff')
            ->get('/admin/bkd')
            ->assertOk()
            ->assertSee('Kelompok Data SISTER')
            ->assertSee('Riwayat Pendidikan')
            ->assertSee('Organisasi Profesi')
            ->assertSee('belum ada layar untuk mengisinya');
    });

    it('mengunduh kelompok yang tersedia', function () {
        Dosen::factory()->create(['nidn' => '0412058901']);

        $respons = $this->actingAs($this->staf, 'staff')
            ->get('/admin/bkd/ekspor/sister/riwayat_pendidikan');

        $respons->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    });

    it('membalas 404 untuk kelompok yang belum dapat diekspor, bukan berkas kosong', function () {
        // Berkas berisi baris judul saja akan terbaca sebagai "kampus ini tidak
        // punya data jenis itu" — pernyataan yang berbeda, dan tidak benar.
        $this->actingAs($this->staf, 'staff')
            ->get('/admin/bkd/ekspor/sister/keluarga')
            ->assertNotFound();
    });
});

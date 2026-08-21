<?php

declare(strict_types=1);

use App\Enums\JenisBeasiswa;
use App\Enums\SemesterType;
use App\Enums\StatusPenerima;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Kemahasiswaan\StatusMahasiswa;
use App\Models\Keuangan\Beasiswa;
use App\Models\Keuangan\BeasiswaPenerima;
use App\Models\Sdm\Staff;
use App\Services\Keuangan\EksporKipk;
use Database\Seeders\RolePermissionSeeder;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Laporan semester KIP Kuliah.
 *
 * Datanya seluruhnya sudah ada di status_mahasiswa. Yang diuji di sini adalah
 * hal-hal yang membuat laporan semacam ini berbahaya bila dibiarkan rapi:
 * penerima yang hilang dari berkas, nilai yang belum final tetapi terbaca
 * final, dan data ekonomi yang ikut terbawa karena kebetulan tersedia.
 */
beforeEach(function () {
    $this->term = TahunAkademik::factory()->term(2026, SemesterType::Ganjil)->berjalan()->aktif()->create();

    $this->skema = Beasiswa::create([
        'kode' => 'KIPK-2026',
        'nama' => 'KIP Kuliah',
        'jenis' => JenisBeasiswa::Eksternal,
        'penyandang' => 'Puslapdik',
        'persen' => 100,
        'is_active' => true,
    ]);

    config(['kipk.beasiswa_kode' => ['KIPK-2026']]);

    $this->kipk = app(EksporKipk::class);
});

/** Seorang penerima, dengan atau tanpa baris status semester ini. */
function penerimaKipk(?array $status = [], array $atributMahasiswa = []): Mahasiswa
{
    $mahasiswa = Mahasiswa::factory()->create($atributMahasiswa);

    BeasiswaPenerima::create([
        'beasiswa_id' => test()->skema->id,
        'mahasiswa_id' => $mahasiswa->id,
        'tahun_akademik_mulai_id' => test()->term->id,
        'status' => StatusPenerima::Aktif,
    ]);

    if ($status !== null) {
        StatusMahasiswa::create(array_merge([
            'mahasiswa_id' => $mahasiswa->id,
            'tahun_akademik_id' => test()->term->id,
            'status' => 'A',
            'semester_ke' => 3,
            'sks_semester' => 21,
            'sks_kumulatif' => 62,
            'ips' => 3.4,
            'ipk' => 3.25,
            'is_final' => true,
        ], $status));
    }

    return $mahasiswa;
}

/** Isi berkas yang dialirkan; StreamedResponse menulis ke keluaran, bukan ke badan respons. */
function isiUnduhan(StreamedResponse $respons): string
{
    ob_start();
    $respons->sendContent();

    return (string) ob_get_clean();
}

describe('skema belum ditetapkan', function () {
    it('menolak berjalan alih-alih menghasilkan berkas kosong', function () {
        /*
         * Berkas berisi nol penerima terbaca sebagai "tidak ada yang menerima
         * KIP Kuliah di kampus ini". Kebenarannya: aplikasinya tidak pernah
         * diberi tahu skema mana yang KIP Kuliah.
         */
        config(['kipk.beasiswa_kode' => []]);

        expect($this->kipk->siap())->toBeFalse()
            ->and(fn () => $this->kipk->baris($this->term))
            ->toThrow(RuntimeException::class, 'belum ditetapkan');
    });

    it('menandai kode skema yang tidak ada di tabel beasiswa', function () {
        // Salah ketik di config akan menghasilkan laporan tanpa siapa pun.
        // Lebih baik ia muncul sebagai "kode ini tidak ada".
        config(['kipk.beasiswa_kode' => ['KIPK-2026', 'SALAH-KETIK']]);

        expect($this->kipk->skema())->toBe([
            'KIPK-2026' => 'KIP Kuliah',
            'SALAH-KETIK' => null,
        ]);
    });
});

describe('penerima yang datanya tidak utuh', function () {
    it('tetap memasukkan penerima tanpa baris status, beserta keterangannya', function () {
        /*
         * Membuangnya dari berkas adalah cara seseorang terus menerima dana
         * sementara tidak ada yang menyadari ia berhenti kuliah.
         */
        penerimaKipk(status: null);

        $baris = $this->kipk->baris($this->term);

        expect($baris)->toHaveCount(1)
            ->and($baris[0]['ada_status'])->toBeFalse()
            ->and($baris[0]['ips'])->toBeNull();

        expect(isiUnduhan($this->kipk->csv($this->term)))
            ->toContain('TIDAK ADA STATUS SEMESTER INI');
    });

    it('menandai nilai yang belum final', function () {
        // Melaporkannya tanpa keterangan berarti mengirim angka yang akan
        // dibantah kampusnya sendiri dua minggu kemudian.
        penerimaKipk(['is_final' => false]);

        expect($this->kipk->baris($this->term)[0]['final'])->toBeFalse()
            ->and(isiUnduhan($this->kipk->csv($this->term)))->toContain('Nilai belum final');
    });

    it('meringkas keduanya untuk layar', function () {
        penerimaKipk();
        penerimaKipk(status: null);
        penerimaKipk(['is_final' => false]);

        expect($this->kipk->ringkas($this->term))->toBe([
            'penerima' => 3,
            'tanpa_status' => 1,
            'belum_final' => 1,
        ]);
    });
});

describe('privasi', function () {
    it('tidak membawa NIK maupun alamat rumah', function () {
        /*
         * KIP Kuliah berbasis kemampuan ekonomi, jadi justru inilah berkas yang
         * paling menggoda untuk diisi data ekonomi. Ia beredar lewat surel dan
         * folder bersama — saluran yang berbeda dari pengiriman resmi.
         */
        penerimaKipk(atributMahasiswa: [
            'nik' => '3273010101900001',
            'alamat' => 'Jalan Rahasia Nomor 7',
        ]);

        $isi = isiUnduhan($this->kipk->csv($this->term));

        expect($isi)->not->toContain('3273010101900001')
            ->and($isi)->not->toContain('Jalan Rahasia')
            ->and($isi)->not->toContain('NIK');
    });
});

describe('layar dan rute', function () {
    it('menyatakan sebabnya ketika skemanya belum ditetapkan', function () {
        config(['kipk.beasiswa_kode' => []]);

        $this->seed(RolePermissionSeeder::class);
        $staf = Staff::factory()->create();
        $staf->assignRole('super-admin');

        $this->actingAs($staf, 'staff')
            ->get('/admin/beasiswa')
            ->assertOk()
            ->assertSee('Belum dapat dibuat')
            ->assertSee('config/kipk.php');

        // 404, bukan berkas berisi baris judul saja.
        $this->actingAs($staf, 'staff')
            ->get('/admin/beasiswa/kipk/ekspor')
            ->assertNotFound();
    });

    it('mengunduh laporannya ketika skemanya sudah ditetapkan', function () {
        penerimaKipk();

        $this->seed(RolePermissionSeeder::class);
        $staf = Staff::factory()->create();
        $staf->assignRole('super-admin');

        $this->actingAs($staf, 'staff')
            ->get('/admin/beasiswa/kipk/ekspor')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    });

    it('menampilkan peringatan di layar ketika ada penerima tanpa status', function () {
        // Angkanya saja tidak cukup: yang membaca layar perlu tahu bahwa
        // penerima yang berhenti kuliah tanpa tercatat adalah penerima yang
        // dananya terus berjalan.
        penerimaKipk();
        penerimaKipk(status: null);

        $this->seed(RolePermissionSeeder::class);
        $staf = Staff::factory()->create();
        $staf->assignRole('super-admin');

        $this->actingAs($staf, 'staff')
            ->get('/admin/beasiswa')
            ->assertOk()
            ->assertSee('Laporan Semester KIP Kuliah')
            ->assertSee('Tanpa status semester ini')
            ->assertSee('dananya terus berjalan');
    });
});

<?php

declare(strict_types=1);

use App\Enums\JenisPoin;
use App\Enums\SemesterType;
use App\Enums\StudentStatus;
use App\Exceptions\AturanAkademikException;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Kemahasiswaan\PoinKategori;
use App\Models\Kemahasiswaan\PoinMahasiswa;
use App\Models\Sdm\Staff;
use App\Services\Kemahasiswaan\PoinKemahasiswaanService;
use App\Services\Kemahasiswaan\YudisiumService;
use Database\Seeders\RolePermissionSeeder;

/**
 * Poin kemahasiswaan.
 *
 * The property worth defending is that the two ledgers are never netted. Most
 * of these tests are here to make sure nobody adds that convenience later.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->term = TahunAkademik::factory()->term(2026, SemesterType::Ganjil)->berjalan()->aktif()->create();
    $this->prodi = Prodi::factory()->create();

    $this->poin = app(PoinKemahasiswaanService::class);

    $this->staf = Staff::factory()->create();
    $this->staf->assignRole('super-admin');
});

function kategoriPoin(JenisPoin $jenis, int $poin, bool $wajibBukti = false): PoinKategori
{
    return PoinKategori::create([
        'kode' => strtoupper($jenis->value).'-'.$poin.'-'.uniqid(),
        'nama' => $jenis->label().' '.$poin,
        'jenis' => $jenis,
        'tingkat' => $jenis === JenisPoin::Prestasi ? 'nasional' : 'sedang',
        'poin' => $poin,
        'wajib_bukti' => $wajibBukti,
    ]);
}

function mahasiswaPoin(): Mahasiswa
{
    return Mahasiswa::factory()->create([
        'prodi_id' => test()->prodi->id,
        'status' => StudentStatus::Aktif,
    ]);
}

/** Records a line and verifies it in one step, for tests about totals. */
function poinDiakui(Mahasiswa $mahasiswa, JenisPoin $jenis, int $poin): PoinMahasiswa
{
    $baris = test()->poin->catat($mahasiswa, kategoriPoin($jenis, $poin), [
        'tanggal' => now()->toDateString(),
        'judul' => 'Uji '.$jenis->value,
    ], test()->staf);

    return test()->poin->verifikasi($baris, test()->staf);
}

describe('dua buku besar terpisah', function () {
    it('tidak pernah mengurangi prestasi dengan pelanggaran', function () {
        /*
         * Penjaga inti modul ini. Menjumlahkan keduanya berarti membiarkan
         * mahasiswa menebus sanksi dengan kemenangan lomba — dan tidak ada
         * bagian kemahasiswaan yang bermaksud begitu dengan angka mana pun.
         */
        $mahasiswa = mahasiswaPoin();

        poinDiakui($mahasiswa, JenisPoin::Prestasi, 60);
        poinDiakui($mahasiswa, JenisPoin::Pelanggaran, 60);

        $rekap = $this->poin->rekap($mahasiswa);

        expect($rekap['prestasi'])->toBe(60)
            ->and($rekap['pelanggaran'])->toBe(60);
    });

    it('tidak menyediakan angka poin bersih sama sekali', function () {
        // Begitu metode semacam itu ada, seseorang akan memanggilnya dan
        // mencetak hasilnya di suatu tempat.
        $metode = get_class_methods(PoinKemahasiswaanService::class);

        expect($metode)->not->toContain('bersih')
            ->and($metode)->not->toContain('total')
            ->and($metode)->not->toContain('saldo');
    });
});

describe('hanya yang terverifikasi terhitung', function () {
    it('mengabaikan prestasi yang belum diverifikasi', function () {
        // Klaim yang belum diperiksa tidak boleh mendorong seseorang melewati
        // garis kelulusan.
        $mahasiswa = mahasiswaPoin();

        $this->poin->catat($mahasiswa, kategoriPoin(JenisPoin::Prestasi, 40), [
            'tanggal' => now()->toDateString(),
            'judul' => 'Juara lomba',
        ], $this->staf);

        expect($this->poin->rekap($mahasiswa)['prestasi'])->toBe(0);
    });

    it('mengabaikan pelanggaran yang belum diverifikasi', function () {
        // Arah sebaliknya sama pentingnya: tuduhan yang belum diperiksa tidak
        // boleh tercatat atas nama seseorang seolah sudah terbukti.
        $mahasiswa = mahasiswaPoin();

        $this->poin->catat($mahasiswa, kategoriPoin(JenisPoin::Pelanggaran, 75), [
            'tanggal' => now()->toDateString(),
            'judul' => 'Dilaporkan',
        ], $this->staf);

        expect($this->poin->rekap($mahasiswa)['pelanggaran'])->toBe(0)
            ->and($this->poin->rekap($mahasiswa)['temuan'])->toBeNull();
    });
});

describe('nilai poin dibekukan', function () {
    it('tidak ikut berubah ketika katalog diubah harganya', function () {
        $mahasiswa = mahasiswaPoin();
        $kategori = kategoriPoin(JenisPoin::Prestasi, 40);

        $baris = $this->poin->catat($mahasiswa, $kategori, [
            'tanggal' => now()->toDateString(),
            'judul' => 'Juara 1 nasional',
        ], $this->staf);

        $this->poin->verifikasi($baris, $this->staf);

        // Kampus menaikkan harganya tahun berikutnya.
        $kategori->update(['poin' => 80]);

        expect($this->poin->rekap($mahasiswa)['prestasi'])->toBe(40);
    });
});

describe('pencatatan', function () {
    it('menolak kategori yang mensyaratkan bukti tanpa bukti', function () {
        $mahasiswa = mahasiswaPoin();

        expect(fn () => $this->poin->catat($mahasiswa, kategoriPoin(JenisPoin::Prestasi, 40, true), [
            'tanggal' => now()->toDateString(),
            'judul' => 'Klaim tanpa lampiran',
        ], $this->staf))->toThrow(AturanAkademikException::class, 'bukti');
    });

    it('menolak kategori yang sudah tidak aktif', function () {
        $mahasiswa = mahasiswaPoin();
        $kategori = kategoriPoin(JenisPoin::Prestasi, 40);
        $kategori->update(['is_active' => false]);

        expect(fn () => $this->poin->catat($mahasiswa, $kategori, [
            'tanggal' => now()->toDateString(),
            'judul' => 'Kategori pensiun',
        ], $this->staf))->toThrow(AturanAkademikException::class, 'tidak aktif');
    });

    it('menyimpan catatan yang ditolak beserta alasannya', function () {
        /*
         * Klaim prestasi yang lenyap membuat mahasiswa tidak tahu apakah ia
         * ditolak atau hilang; tuduhan yang lenyap tidak meninggalkan catatan
         * bahwa kampus pernah memeriksanya dan tidak menemukan apa-apa.
         */
        $mahasiswa = mahasiswaPoin();

        $baris = $this->poin->catat($mahasiswa, kategoriPoin(JenisPoin::Prestasi, 40), [
            'tanggal' => now()->toDateString(),
            'judul' => 'Sertifikat tidak sah',
        ], $this->staf);

        $this->poin->tolak($baris, $this->staf, 'Sertifikat tidak dapat diverifikasi ke penyelenggara.');

        expect($baris->refresh()->ditolak())->toBeTrue()
            ->and($baris->alasan_tolak)->toContain('penyelenggara')
            ->and(PoinMahasiswa::count())->toBe(1);
    });

    it('menolak verifikasi ganda', function () {
        $mahasiswa = mahasiswaPoin();
        $baris = poinDiakui($mahasiswa, JenisPoin::Prestasi, 10);

        expect(fn () => $this->poin->verifikasi($baris, $this->staf))
            ->toThrow(AturanAkademikException::class, 'sudah diverifikasi');
    });
});

describe('temuan pelanggaran', function () {
    it('menyebut ambang yang dilewati, bukan sanksinya', function () {
        // Temuan, bukan sanksi. Sanksi adalah keputusan orang.
        $mahasiswa = mahasiswaPoin();

        poinDiakui($mahasiswa, JenisPoin::Pelanggaran, 60);

        expect($this->poin->rekap($mahasiswa)['temuan'])
            ->toContain('Perlu pembinaan')
            ->toContain('ambang 50');
    });

    it('memilih ambang terberat yang terlewati', function () {
        $mahasiswa = mahasiswaPoin();

        poinDiakui($mahasiswa, JenisPoin::Pelanggaran, 120);

        expect($this->poin->rekap($mahasiswa)['temuan'])->toContain('Terancam sanksi berat');
    });
});

describe('syarat kelulusan', function () {
    it('tidak menampilkan baris poin ketika kampus tidak menetapkan minimum', function () {
        /*
         * Dihilangkan, bukan diloloskan otomatis — pola yang sama dengan tugas
         * akhir pada prodi yang tidak mewajibkannya. Baris hijau untuk syarat
         * yang tidak pernah ada membuat persentase kelulusan berbohong.
         */
        config(['kemahasiswaan.prestasi.minimum_lulus' => 0]);

        $syarat = app(YudisiumService::class)->periksaSyarat(mahasiswaPoin());

        expect(collect($syarat->rincian)->pluck('kode'))->not->toContain('poin_kemahasiswaan');
    });

    it('menampilkan baris poin ketika kampus menetapkannya', function () {
        config(['kemahasiswaan.prestasi.minimum_lulus' => 50]);

        $mahasiswa = mahasiswaPoin();
        poinDiakui($mahasiswa, JenisPoin::Prestasi, 20);

        $baris = collect(app(YudisiumService::class)->periksaSyarat($mahasiswa)->rincian)
            ->firstWhere('kode', 'poin_kemahasiswaan');

        expect($baris)->not->toBeNull()
            ->and($baris['terpenuhi'])->toBeFalse()
            ->and($baris['keterangan'])->toBe('20 dari 50 poin terverifikasi');
    });

    it('tidak membiarkan pelanggaran ikut memenuhi syarat poin prestasi', function () {
        /*
         * Arah inilah yang berbahaya, dan versi pertama tes ini melewatkannya.
         *
         * Semula ia memberi 60 prestasi + 100 pelanggaran dan memastikan syarat
         * tetap terpenuhi — yang lolos juga tanpa pemisahan buku besar, karena
         * 160 pun melewati 50. Tidak ada yang dipaku.
         *
         * Yang harus dijaga: pelanggaran **menggenapi** syaratnya. Nol prestasi
         * dan seratus pelanggaran adalah mahasiswa yang belum memenuhi apa pun.
         */
        config(['kemahasiswaan.prestasi.minimum_lulus' => 50]);

        $mahasiswa = mahasiswaPoin();
        poinDiakui($mahasiswa, JenisPoin::Pelanggaran, 100);

        $baris = collect(app(YudisiumService::class)->periksaSyarat($mahasiswa)->rincian)
            ->firstWhere('kode', 'poin_kemahasiswaan');

        expect($baris['terpenuhi'])->toBeFalse()
            ->and($baris['keterangan'])->toBe('0 dari 50 poin terverifikasi');
    });
});

describe('layar', function () {
    it('menampilkan dua angka berdampingan tanpa menjumlahkannya', function () {
        $mahasiswa = mahasiswaPoin();
        poinDiakui($mahasiswa, JenisPoin::Prestasi, 60);
        poinDiakui($mahasiswa, JenisPoin::Pelanggaran, 30);

        $this->actingAs($this->staf, 'staff')
            ->get('/admin/poin-kemahasiswaan?nim='.$mahasiswa->nim)
            ->assertOk()
            ->assertSee('Prestasi & kegiatan', false)
            ->assertSee('Pelanggaran')
            ->assertSee('60')
            ->assertSee('30');
    });

    it('menolak staf tanpa izin kelola memverifikasi', function () {
        $mahasiswa = mahasiswaPoin();

        $baris = $this->poin->catat($mahasiswa, kategoriPoin(JenisPoin::Prestasi, 40), [
            'tanggal' => now()->toDateString(),
            'judul' => 'Klaim',
        ], $this->staf);

        $keuangan = Staff::factory()->create();
        $keuangan->assignRole('keuangan');

        $this->actingAs($keuangan, 'staff')
            ->post('/admin/poin-kemahasiswaan/'.$baris->uuid.'/verifikasi')
            ->assertForbidden();
    });
});

<?php

declare(strict_types=1);

use App\Enums\SemesterType;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Sdm\Staff;
use App\Services\Lkps\PerakitBorang;
use Database\Seeders\RolePermissionSeeder;

/**
 * Perakitan besaran kanonis menjadi tabel borang.
 *
 * Lapisan ini tipis dengan sengaja: yang dihitung ada di IndikatorLkps, yang
 * di sini hanya penempatan. Karena itu yang diuji terutama dua hal yang mudah
 * salah pada lapisan penempatan — nomor tabel yang tidak boleh dikarang, dan
 * tabel tak terisi yang tidak boleh keluar sebagai baris kosong.
 */
beforeEach(function () {
    $this->term = TahunAkademik::factory()->term(2026, SemesterType::Ganjil)->berjalan()->aktif()->create();
    $this->prodi = Prodi::factory()->create(['kode' => '55201']);
    $this->perakit = app(PerakitBorang::class);
});

describe('nomor tabel', function () {
    it('tidak mengarang nomor ketika config-nya kosong', function () {
        /*
         * Nomor tabel berbeda antar-LAM dan berubah antar-revisi instrumen.
         * Nomor yang tampak masuk akal lebih buruk daripada nomor kosong:
         * seseorang akan menyalinnya ke borang sungguhan tanpa memeriksa.
         */
        $tabel = collect($this->perakit->rakit($this->prodi, $this->term));

        expect($tabel->pluck('nomor')->filter()->all())->toBe([]);
    });

    it('memakai nomor dari config ketika kampus mengisinya', function () {
        config(['lkps.borang.seleksi.nomor' => '2.a']);

        $seleksi = collect($this->perakit->rakit($this->prodi, $this->term))
            ->firstWhere('kunci', 'seleksi');

        expect($seleksi['nomor'])->toBe('2.a');
    });
});

describe('tabel yang tidak terisi', function () {
    it('membawa alasan alih-alih baris kosong', function () {
        $tabel = collect($this->perakit->rakit($this->prodi, $this->term));

        $penelitian = $tabel->firstWhere('kunci', 'penelitian_pkm');

        expect($penelitian['terisi'])->toBeFalse()
            ->and($penelitian['baris'])->toBe([])
            ->and($penelitian['alasan'])->toContain('bukan basis data penelitian');
    });

    it('menghilangkan tabel kepuasan ketika EDOM ditetapkan sebagai proksi', function () {
        config(['lkps.kepuasan.sumber' => 'edom']);

        $kunci = collect($this->perakit->rakit($this->prodi, $this->term))->pluck('kunci');

        expect($kunci)->not->toContain('kepuasan_layanan');
    });
});

describe('sel kosong', function () {
    it('menulis tanda pisah, bukan nol, untuk angka yang tidak ada', function () {
        // Prodi tanpa lulusan dan tanpa dosen. Nol di kolom IPK adalah tuduhan
        // terhadap prodinya; tanda pisah adalah keterangan bahwa tidak ada
        // yang diukur.
        $tabel = collect($this->perakit->rakit($this->prodi, $this->term));

        $lulusan = $tabel->firstWhere('kunci', 'lulusan');
        $rasio = $tabel->firstWhere('kunci', 'mahasiswa_dosen');

        expect($lulusan['baris'][0][3])->toBe('—')
            ->and($rasio['baris'][0][3])->toBe('—')
            ->and($rasio['catatan'])->toContain('belum ada dosen tetap');
    });

    it('menderetkan tahun sebanyak yang diminta config', function () {
        config(['lkps.tahun_deret' => 5]);

        $seleksi = collect($this->perakit->rakit($this->prodi, $this->term))
            ->firstWhere('kunci', 'seleksi');

        expect($seleksi['baris'])->toHaveCount(5)
            ->and($seleksi['baris'][4][0])->toBe(2026);
    });
});

describe('layar', function () {
    it('menampilkan borang beserta definisi yang dipakai menghitungnya', function () {
        $this->seed(RolePermissionSeeder::class);
        $staf = Staff::factory()->create();
        $staf->assignRole('super-admin');

        $this->actingAs($staf, 'staff')
            ->get('/admin/lkps')
            ->assertOk()
            ->assertSee('Definisi masih sementara')
            ->assertSee('Seleksi Mahasiswa Baru')
            ->assertSee('Tidak diisi');
    });

    it('mengekspor CSV yang menyebut tabel tak terisi, bukan menghilangkannya', function () {
        /*
         * Menghilangkannya lebih buruk daripada tidak berguna: yang menempel
         * berkas ini ke borang akan mendapati kelompoknya hilang dan mengira
         * kelompok itu memang tidak ditanyakan.
         */
        $this->seed(RolePermissionSeeder::class);
        $staf = Staff::factory()->create();
        $staf->assignRole('super-admin');

        $respons = $this->actingAs($staf, 'staff')->get('/admin/lkps/ekspor');
        $respons->assertOk();

        ob_start();
        $respons->baseResponse->sendContent();
        $isi = (string) ob_get_clean();

        expect($isi)->toContain('TIDAK DIISI')
            ->and($isi)->toContain('Tracer Study');
    });
});

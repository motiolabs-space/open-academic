<?php

declare(strict_types=1);

use App\Enums\SemesterType;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Feeder\FeederMapping;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Kemahasiswaan\StatusMahasiswa;
use App\Models\Kemahasiswaan\Yudisium;
use App\Models\Pmb\PmbGelombang;
use App\Models\Pmb\PmbPendaftar;
use App\Models\Sdm\Dosen;
use App\Services\Lkps\IndikatorLkps;

/**
 * Besaran LKPS, dihitung sekali dan dapat dipakai borang LAM mana pun.
 *
 * Yang paling dijaga di sini bukan rumusnya melainkan angka nol dan angka
 * kosong. Nol di tabel akreditasi adalah pernyataan tentang kampusnya, bukan
 * tentang perangkat lunaknya — dan keduanya mudah tertukar.
 */
beforeEach(function () {
    $this->term = TahunAkademik::factory()->term(2026, SemesterType::Ganjil)->berjalan()->aktif()->create();
    $this->prodi = Prodi::factory()->create();
    $this->lkps = app(IndikatorLkps::class);
});

/** Sejumlah pendaftar pada satu gelombang, dengan status apa adanya. */
function pendaftarPmb(array $statusDanJumlah): void
{
    $gelombang = PmbGelombang::create([
        'tahun_akademik_id' => test()->term->id,
        'kode' => 'G1-2026',
        'nama' => 'Gelombang 1',
        'tanggal_mulai' => '2026-02-01',
        'tanggal_selesai' => '2026-05-31',
    ]);

    $nomor = 1;

    foreach ($statusDanJumlah as $status => $jumlah) {
        foreach (range(1, $jumlah) as $_) {
            PmbPendaftar::create([
                'pmb_gelombang_id' => $gelombang->id,
                'nomor_pendaftaran' => 'PMB-'.str_pad((string) $nomor++, 4, '0', STR_PAD_LEFT),
                'nama' => 'Calon '.$nomor,
                'email' => 'calon'.$nomor.'@contoh.test',
                'prodi_pilihan_1_id' => test()->prodi->id,
                'status' => $status,
            ]);
        }
    }
}

describe('keketatan', function () {
    it('menghitung pendaftar sejak tahap yang ditetapkan, termasuk tahap sesudahnya', function () {
        /*
         * Corongnya berurutan: seorang mahasiswa yang sudah terdaftar pernah
         * melewati verifikasi. Menyaring "tepat sama dengan verifikasi" akan
         * membuang semua orang yang berhasil maju — dan justru menghasilkan
         * keketatan yang mustahil.
         */
        pendaftarPmb([
            'mendaftar' => 5,
            'verifikasi' => 3,
            'seleksi' => 2,
            'tidak_lulus' => 4,
            'lulus' => 1,
            'mahasiswa' => 10,
        ]);

        $hasil = $this->lkps->keketatan($this->prodi, 2026);

        // Bawaan 'verifikasi': 3 + 2 + 4 + 1 + 10 = 20. Lima yang berhenti di
        // 'mendaftar' tidak dihitung.
        expect($hasil['pendaftar'])->toBe(20)
            ->and($hasil['diterima'])->toBe(11)
            ->and($hasil['keketatan'])->toBe(round(20 / 11, 2));
    });

    it('mengikuti perubahan definisi pendaftar', function () {
        pendaftarPmb(['mendaftar' => 5, 'mahasiswa' => 10]);

        config(['lkps.keketatan.pendaftar_sejak' => 'mendaftar']);

        expect($this->lkps->keketatan($this->prodi, 2026)['pendaftar'])->toBe(15);
    });

    it('mengembalikan null ketika belum ada yang diterima', function () {
        // Rasio berpenyebut nol tidak terdefinisi. Mencetak 1,0 akan
        // melaporkan keketatan sempurna bagi prodi yang tidak menerima
        // siapa pun.
        pendaftarPmb(['verifikasi' => 4]);

        expect($this->lkps->keketatan($this->prodi, 2026)['keketatan'])->toBeNull();
    });
});

describe('dosen & mahasiswa', function () {
    it('menghitung dosen tetap sesuai definisi yang dikonfigurasi', function () {
        Dosen::factory()->count(3)->create([
            'prodi_id' => $this->prodi->id,
            'status_kepegawaian' => 'tetap',
        ]);
        Dosen::factory()->create([
            'prodi_id' => $this->prodi->id,
            'status_kepegawaian' => 'tidak_tetap',
        ]);

        expect($this->lkps->dtps($this->prodi))->toBe(3);

        // Definisinya keputusan kampus, jadi ia harus benar-benar mengikat.
        config(['lkps.dtps.status_kepegawaian' => ['tetap', 'tidak_tetap']]);
        expect($this->lkps->dtps($this->prodi))->toBe(4);
    });

    it('mengeluarkan dosen praktisi kecuali diminta sebaliknya', function () {
        Dosen::factory()->create(['prodi_id' => $this->prodi->id, 'status_kepegawaian' => 'tetap']);
        Dosen::factory()->create([
            'prodi_id' => $this->prodi->id,
            'status_kepegawaian' => 'tetap',
            'is_praktisi' => true,
        ]);

        expect($this->lkps->dtps($this->prodi))->toBe(1);

        config(['lkps.dtps.sertakan_praktisi' => true]);
        expect($this->lkps->dtps($this->prodi))->toBe(2);
    });

    it('mengembalikan null, bukan pembagian nol, ketika prodi belum punya dosen tetap', function () {
        /*
         * "Tidak ada dosen tercatat" adalah temuannya. Rasio yang dipaksa
         * menjadi 0 akan terbaca sebagai prodi tanpa mahasiswa, dan itu
         * pernyataan yang berbeda.
         */
        $mahasiswa = Mahasiswa::factory()->create(['prodi_id' => $this->prodi->id]);
        StatusMahasiswa::create([
            'mahasiswa_id' => $mahasiswa->id,
            'tahun_akademik_id' => $this->term->id,
            'status' => 'A',
            'semester_ke' => 1,
        ]);

        expect($this->lkps->rasioDosenMahasiswa($this->prodi, $this->term))->toBeNull();
    });

    it('menghitung rasio ketika keduanya ada', function () {
        Dosen::factory()->count(2)->create([
            'prodi_id' => $this->prodi->id,
            'status_kepegawaian' => 'tetap',
        ]);

        foreach (range(1, 10) as $i) {
            $m = Mahasiswa::factory()->create(['prodi_id' => $this->prodi->id]);
            StatusMahasiswa::create([
                'mahasiswa_id' => $m->id,
                'tahun_akademik_id' => $this->term->id,
                'status' => $i <= 8 ? 'A' : 'C',
                'semester_ke' => 1,
            ]);
        }

        // Cuti tidak dihitung aktif pada definisi bawaan: 8 / 2.
        expect($this->lkps->rasioDosenMahasiswa($this->prodi, $this->term))->toBe(4.0);
    });
});

describe('lulusan', function () {
    it('menghitung masa studi dari semester_ke milik kampus', function () {
        $mahasiswa = Mahasiswa::factory()->create([
            'prodi_id' => $this->prodi->id,
            'term_masuk' => '20221',
        ]);

        StatusMahasiswa::create([
            'mahasiswa_id' => $mahasiswa->id,
            'tahun_akademik_id' => $this->term->id,
            'status' => 'L',
            'semester_ke' => 9,
        ]);

        Yudisium::create([
            'mahasiswa_id' => $mahasiswa->id,
            'tahun_akademik_id' => $this->term->id,
            'nomor_sk' => 'SK/1',
            'tanggal_yudisium' => '2026-09-01',
            'tanggal_lulus' => '2026-09-01',
            'total_sks' => 146,
            'ipk' => 3.4,
            'predikat' => 'Sangat Memuaskan',
            'status' => 'ditetapkan',
        ]);

        $hasil = $this->lkps->lulusan($this->prodi, 2026);

        // Sembilan semester melewati batas delapan untuk S1.
        expect($hasil['jumlah'])->toBe(1)
            ->and($hasil['masa_studi_rata'])->toBe(9.0)
            ->and($hasil['tepat_waktu'])->toBe(0)
            ->and($hasil['ipk_rata'])->toBe(3.4);
    });

    it('membiarkan angka kosong tetap kosong ketika tidak ada lulusan', function () {
        // Nol lulusan berarti IPK rata-rata TIDAK ADA, bukan 0,00 — dan 0,00
        // di tabel akreditasi adalah tuduhan terhadap prodinya.
        $hasil = $this->lkps->lulusan($this->prodi, 2026);

        expect($hasil['jumlah'])->toBe(0)
            ->and($hasil['ipk_rata'])->toBeNull()
            ->and($hasil['masa_studi_rata'])->toBeNull();
    });

    it('menyertakan catatan ketika alih jenjang tidak dapat dipisahkan', function () {
        /*
         * Config meminta alih jenjang dikeluarkan, tetapi pemetaan
         * jenis_daftar kosong — jadi pengecualiannya diam-diam tidak terjadi.
         * Yang tidak boleh terjadi adalah angkanya keluar tanpa memberi tahu.
         */
        $mahasiswa = Mahasiswa::factory()->create([
            'prodi_id' => $this->prodi->id,
            'term_masuk' => '20221',
            'jalur_masuk' => 'alih-jenjang',
        ]);

        Yudisium::create([
            'mahasiswa_id' => $mahasiswa->id,
            'tahun_akademik_id' => $this->term->id,
            'nomor_sk' => 'SK/2',
            'tanggal_yudisium' => '2026-09-01',
            'tanggal_lulus' => '2026-09-01',
            'total_sks' => 146,
            'ipk' => 3.6,
            'predikat' => 'Sangat Memuaskan',
            'status' => 'ditetapkan',
        ]);

        $hasil = $this->lkps->lulusan($this->prodi, 2026);

        expect($hasil['dikecualikan'])->toBe(0)
            ->and($hasil['catatan'])->toContain('tidak dapat dipisahkan');
    });

    it('mengeluarkan alih jenjang ketika pemetaannya ada', function () {
        FeederMapping::create([
            'group' => 'jenis_daftar',
            'local_value' => 'alih-jenjang',
            'feeder_code' => '4',
        ]);

        $mahasiswa = Mahasiswa::factory()->create([
            'prodi_id' => $this->prodi->id,
            'term_masuk' => '20221',
            'jalur_masuk' => 'alih-jenjang',
        ]);

        Yudisium::create([
            'mahasiswa_id' => $mahasiswa->id,
            'tahun_akademik_id' => $this->term->id,
            'nomor_sk' => 'SK/3',
            'tanggal_yudisium' => '2026-09-01',
            'tanggal_lulus' => '2026-09-01',
            'total_sks' => 146,
            'ipk' => 3.6,
            'predikat' => 'Sangat Memuaskan',
            'status' => 'ditetapkan',
        ]);

        $hasil = $this->lkps->lulusan($this->prodi, 2026);

        // Tetap terhitung sebagai lulusan, tetapi keluar dari populasi
        // ketepatan waktu — dan jumlahnya dilaporkan supaya dapat dicek.
        expect($hasil['jumlah'])->toBe(1)
            ->and($hasil['dikecualikan'])->toBe(1)
            ->and($hasil['masa_studi_rata'])->toBeNull()
            ->and($hasil['catatan'])->toBeNull();
    });
});

describe('yang tidak dapat dihitung', function () {
    it('menyebut tabel yang tidak diisinya beserta alasannya', function () {
        $kosong = $this->lkps->tidakTersedia();

        expect($kosong)->toHaveKeys(['tracer_study', 'penelitian_pkm', 'kepuasan_layanan'])
            ->and($kosong['penelitian_pkm'])->toContain('bukan basis data penelitian');
    });

    it('menghapus kepuasan dari daftar kosong ketika EDOM dipakai sebagai proksi', function () {
        config(['lkps.kepuasan.sumber' => 'edom']);

        expect($this->lkps->tidakTersedia())->not->toHaveKey('kepuasan_layanan');
    });
});

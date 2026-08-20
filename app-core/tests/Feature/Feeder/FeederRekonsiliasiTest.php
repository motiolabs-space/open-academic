<?php

declare(strict_types=1);

use App\Enums\JenisSelisihFeeder;
use App\Enums\SemesterType;
use App\Exceptions\FeederException;
use App\Models\Akademik\KelasKuliah;
use App\Models\Akademik\MataKuliah;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Feeder\FeederDiff;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\Staff;
use App\Services\Feeder\Contracts\FeederClientInterface;
use App\Services\Feeder\FakeFeederClient;
use App\Services\Feeder\FeederRekonsiliasi;
use Database\Seeders\RolePermissionSeeder;

/**
 * Membandingkan isi SIAKAD dengan isi Feeder.
 *
 * Buku besar mencatat apa yang berangkat dari sini; itu pertanyaan yang
 * berbeda dari apa yang ada di sana, dan hanya yang kedua yang dibaca
 * kementerian. Yang diuji di berkas ini terutama adalah kejujurannya: sebuah
 * perbandingan yang tidak dapat berjalan tidak boleh terbaca sebagai cocok.
 */
beforeEach(function () {
    config(['feeder.enabled' => true, 'feeder.driver' => 'fake']);

    $this->fake = new FakeFeederClient;
    $this->app->instance(FeederClientInterface::class, $this->fake);

    // Berjalan, bukan sekadar aktif: seluruh rute /admin dilindungi
    // EnsureTermIsActive, dan tanpa itu tes layarnya berhenti di 503.
    $this->term = TahunAkademik::factory()->term(2026, SemesterType::Ganjil)->berjalan()->aktif()->create();
    $this->prodi = Prodi::factory()->create(['kode_pddikti' => 'a1b2c3d4']);

    $this->rekonsiliasi = app(FeederRekonsiliasi::class);
});

/** Satu kelas kuliah lokal beserta pengampu ber-NIDN. */
function kelasLokal(array $atribut = []): KelasKuliah
{
    $dosen = Dosen::factory()->create(['prodi_id' => test()->prodi->id]);
    $mk = MataKuliah::factory()->create(['prodi_id' => test()->prodi->id, 'kode' => 'MK101']);

    $kelas = KelasKuliah::factory()->create(array_merge([
        'tahun_akademik_id' => test()->term->id,
        'mata_kuliah_id' => $mk->id,
        'prodi_id' => test()->prodi->id,
        'kode' => 'A',
        'sks' => 3,
        'terisi' => 30,
    ], $atribut));

    $kelas->dosen()->attach($dosen->id, ['peran' => 'pengampu']);

    return $kelas->refresh();
}

/** Baris seperti yang dikembalikan Feeder untuk kelas di atas. */
function barisFeeder(array $ubah = []): array
{
    return array_merge([
        'id_semester' => test()->term->kode,
        'id_prodi' => 'a1b2c3d4',
        'id_matkul' => 'MK101',
        'nama_kelas_kuliah' => 'A',
        'sks' => '3.00',
        'jumlah_mahasiswa' => '30',
    ], $ubah);
}

describe('menemukan selisih', function () {
    it('menyatakan cocok ketika kedua sisi sama', function () {
        kelasLokal();
        $this->fake->balas('GetListKelasKuliah', [barisFeeder()]);

        $hasil = $this->rekonsiliasi->bandingkan('kelas_kuliah', $this->term);

        expect($hasil['cocok'])->toBe(1)
            ->and($hasil['hanya_lokal'])->toBe(0)
            ->and($hasil['hanya_feeder'])->toBe(0)
            ->and($hasil['berbeda'])->toBe(0)
            ->and(FeederDiff::count())->toBe(0);
    });

    it('menandai baris yang ada di sini tetapi tidak di Feeder', function () {
        kelasLokal();
        $this->fake->balas('GetListKelasKuliah', []);

        $hasil = $this->rekonsiliasi->bandingkan('kelas_kuliah', $this->term);

        expect($hasil['hanya_lokal'])->toBe(1)
            ->and(FeederDiff::first()->jenis)->toBe(JenisSelisihFeeder::HanyaLokal);
    });

    it('menandai baris yang ada di Feeder tetapi tidak di sini', function () {
        // Inilah yang tidak akan pernah dilihat sinkronisasi satu arah: baris
        // yang dimasukkan langsung di Feeder oleh operator.
        kelasLokal();
        $this->fake->balas('GetListKelasKuliah', [
            barisFeeder(),
            barisFeeder(['nama_kelas_kuliah' => 'B']),
        ]);

        $hasil = $this->rekonsiliasi->bandingkan('kelas_kuliah', $this->term);

        expect($hasil['cocok'])->toBe(1)
            ->and($hasil['hanya_feeder'])->toBe(1)
            ->and(FeederDiff::where('jenis', 'hanya_feeder')->first()->kunci)
            ->toContain('B');
    });

    it('mencatat field mana yang berbeda, bukan sekadar bahwa barisnya berbeda', function () {
        kelasLokal();
        $this->fake->balas('GetListKelasKuliah', [barisFeeder(['jumlah_mahasiswa' => '27'])]);

        $hasil = $this->rekonsiliasi->bandingkan('kelas_kuliah', $this->term);

        expect($hasil['berbeda'])->toBe(1);

        $diff = FeederDiff::where('jenis', 'berbeda')->firstOrFail();

        expect($diff->selisih)->toHaveKey('jumlah_mahasiswa')
            ->and($diff->selisih['jumlah_mahasiswa']['lokal'])->toBe('30')
            ->and($diff->selisih['jumlah_mahasiswa']['feeder'])->toBe('27')
            ->and($diff->ringkasSelisih()[0])->toContain('30 ≠ 27');
    });
});

describe('mengurangi derau', function () {
    it('tidak melaporkan angka yang setara tetapi berbeda tulisannya', function () {
        // Feeder menjawab dalam string. Tanpa aturan ini, 3 melawan "3.00"
        // akan muncul sebagai selisih pada setiap kelas yang pernah dikirim,
        // dan selisih yang sungguhan tenggelam di antaranya.
        kelasLokal();
        $this->fake->balas('GetListKelasKuliah', [barisFeeder(['sks' => '3.000'])]);

        expect($this->rekonsiliasi->bandingkan('kelas_kuliah', $this->term)['cocok'])->toBe(1);
    });

    it('tidak menganggap field yang tidak dikembalikan Feeder sebagai perbedaan', function () {
        // Build Feeder yang lebih lama membawa lebih sedikit field. Itu bukan
        // ketidaksepakatan tentang isinya.
        kelasLokal();
        $baris = barisFeeder();
        unset($baris['jumlah_mahasiswa']);

        $this->fake->balas('GetListKelasKuliah', [$baris]);

        expect($this->rekonsiliasi->bandingkan('kelas_kuliah', $this->term)['cocok'])->toBe(1);
    });
});

describe('menolak berbohong', function () {
    it('melempar galat ketika entitasnya belum punya aksi pembacaan', function () {
        // "nilai" belum ada di bagian reconcile. Jawaban yang benar adalah
        // menolak, bukan mengembalikan nol selisih — keduanya terlihat sama di
        // layar, dan hanya satu yang berarti aman.
        expect(fn () => $this->rekonsiliasi->bandingkan('nilai', $this->term))
            ->toThrow(FeederException::class, 'belum dapat dibandingkan');
    });

    it('menghentikan perbandingan ketika Feeder menolak permintaannya', function () {
        // Nama aksi yang salah mengembalikan nol baris, dan nol baris terlihat
        // persis seperti kesepakatan sempurna.
        kelasLokal();
        $this->fake->tolak('GetListKelasKuliah', 4, 'Aksi tidak dikenal');

        expect(fn () => $this->rekonsiliasi->bandingkan('kelas_kuliah', $this->term))
            ->toThrow(FeederException::class, 'GetListKelasKuliah');

        expect(FeederDiff::count())->toBe(0);
    });

    it('melaporkan baris lokal yang kuncinya tidak lengkap alih-alih membuangnya', function () {
        // Kelas tanpa kode tidak dapat dicocokkan dengan apa pun. Menghitungnya
        // sebagai cocok adalah cara sebuah perbandingan mulai berbohong.
        kelasLokal(['kode' => '']);
        $this->fake->balas('GetListKelasKuliah', []);

        $hasil = $this->rekonsiliasi->bandingkan('kelas_kuliah', $this->term);

        expect($hasil['tanpa_kunci'])->toBe(1)
            ->and($hasil['cocok'])->toBe(0)
            ->and(FeederDiff::first()->jenis)->toBe(JenisSelisihFeeder::TanpaKunci);
    });

    it('menghapus temuan lama ketika perbandingan berikutnya bersih', function () {
        /*
         * Perbandingan yang bersih tidak menulis apa pun. Tanpa penghapusan,
         * temuan kemarin tetap terpampang seolah masih berlaku — sehingga satu
         * -satunya hasil yang diperjuangkan operator justru terlihat tidak
         * mengubah apa pun.
         */
        kelasLokal();

        $this->fake->balas('GetListKelasKuliah', [barisFeeder(['jumlah_mahasiswa' => '27'])]);
        $this->rekonsiliasi->bandingkan('kelas_kuliah', $this->term);
        expect(FeederDiff::count())->toBe(1);

        $this->fake->balas('GetListKelasKuliah', [barisFeeder()]);
        $hasil = $this->rekonsiliasi->bandingkan('kelas_kuliah', $this->term);

        expect($hasil['cocok'])->toBe(1)
            ->and(FeederDiff::count())->toBe(0);
    });

    it('menolak dibandingkan ketika integrasinya dimatikan', function () {
        config(['feeder.enabled' => false]);

        expect(fn () => $this->rekonsiliasi->bandingkan('kelas_kuliah', $this->term))
            ->toThrow(FeederException::class, 'dinonaktifkan');
    });
});

describe('membaca seluruh halaman', function () {
    it('melanjutkan ke halaman berikutnya alih-alih berhenti pada yang pertama', function () {
        /*
         * Jebakannya: pembaca yang tidak menaikkan offset akan tetap lulus
         * setiap tes yang datanya muat dalam satu halaman. Ukuran halaman
         * dikecilkan agar jalur itu benar-benar dilewati.
         */
        config(['feeder.reconcile_page_size' => 2]);

        kelasLokal();

        $this->fake->balas('GetListKelasKuliah', [
            barisFeeder(),
            barisFeeder(['nama_kelas_kuliah' => 'B']),
            barisFeeder(['nama_kelas_kuliah' => 'C']),
            barisFeeder(['nama_kelas_kuliah' => 'D']),
            barisFeeder(['nama_kelas_kuliah' => 'E']),
        ]);

        $hasil = $this->rekonsiliasi->bandingkan('kelas_kuliah', $this->term);

        // Empat kelas asing terbaca, bukan hanya satu dari halaman pertama.
        expect($hasil['cocok'])->toBe(1)
            ->and($hasil['hanya_feeder'])->toBe(4);
    });
});

describe('layar', function () {
    it('menampilkan hasil perbandingan pada konsol Feeder', function () {
        kelasLokal();
        $this->fake->balas('GetListKelasKuliah', [barisFeeder(['jumlah_mahasiswa' => '27'])]);

        $this->seed(RolePermissionSeeder::class);
        $staf = Staff::factory()->create();
        $staf->assignRole('super-admin');

        $this->actingAs($staf, 'staff')
            ->post('/admin/feeder/kelas_kuliah/bandingkan')
            ->assertSessionHas('peringatan');

        $this->actingAs($staf, 'staff')
            ->get('/admin/feeder')
            ->assertOk()
            ->assertSee('Selisih terhadap Feeder')
            ->assertSee('Isinya berbeda');
    });
});

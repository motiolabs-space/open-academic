<?php

declare(strict_types=1);

use App\Enums\SemesterType;
use App\Enums\StudentStatus;
use App\Models\Akademik\KelasKuliah;
use App\Models\Akademik\Kurikulum;
use App\Models\Akademik\MataKuliah;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Dosen;
use App\Support\Portal;
use Database\Seeders\RolePermissionSeeder;

/**
 * Katalog KRS pada katalog besar — paginasi dan pencarian.
 *
 * Diukur 19 Agustus 2026 sebelum perubahan ini: 1.000 baris = 1,92 detik dan
 * **2.235 KB HTML**. Pada jam pembukaan KRS dengan 2.000 mahasiswa itu ±4,4 GB
 * yang harus melewati jaringan kampus dalam hitungan menit. Lihat
 * `docs/KAPASITAS.md`.
 *
 * Yang dipaku di sini bukan kecepatannya — waktu tidak dapat diuji dengan
 * andal — melainkan **batas jumlah baris** dan **pengurutan yang benar lintas
 * halaman**. Keduanya yang menjaga angka itu tidak kembali.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->term = TahunAkademik::factory()->term(2026, SemesterType::Ganjil)->berjalan()->aktif()->create();
    Portal::lupakanTerm();

    $prodi = Prodi::factory()->create();
    $this->kurikulum = Kurikulum::factory()->create(['prodi_id' => $prodi->id]);

    $this->mahasiswa = Mahasiswa::factory()->create([
        'prodi_id' => $prodi->id,
        'kurikulum_id' => $this->kurikulum->id,
        'dosen_wali_id' => Dosen::factory()->create(['prodi_id' => $prodi->id])->id,
        'status' => StudentStatus::Aktif,
    ]);
    $this->mahasiswa->assignRole('mahasiswa');

    $this->prodiId = $prodi->id;
});

/** Membuka N mata kuliah berkode berurutan, masing-masing satu kelas. */
function katalogUji(int $prodiId, Kurikulum $kurikulum, int $termId, int $jumlah): void
{
    for ($i = 1; $i <= $jumlah; $i++) {
        $mk = MataKuliah::factory()->create([
            'prodi_id' => $prodiId,
            'sks' => 3,
            'kode' => sprintf('KAT%03d', $i),
            'nama' => sprintf('Mata Kuliah Uji %03d', $i),
        ]);

        $kurikulum->mataKuliah()->attach($mk->id, ['semester' => 1, 'jenis' => 'wajib']);

        KelasKuliah::factory()->create([
            'tahun_akademik_id' => $termId,
            'mata_kuliah_id' => $mk->id,
            'prodi_id' => $prodiId,
            'sks' => 3,
            'kode' => 'A',
            'kuota' => 40,
        ]);
    }
}

it('membatasi katalog pada satu halaman meski kelasnya jauh lebih banyak', function () {
    katalogUji($this->prodiId, $this->kurikulum, $this->term->id, 60);

    $respons = $this->actingAs($this->mahasiswa, 'mahasiswa')->get('/mahasiswa/krs');

    $respons->assertOk();

    // 25 per halaman. Tanpa batas ini seluruh 60 dirender, dan pada kampus
    // sungguhan angkanya ribuan.
    $katalog = $respons->viewData('katalog');

    expect($katalog->count())->toBe(25)
        ->and($katalog->total())->toBe(60)
        ->and($katalog->hasPages())->toBeTrue();
});

it('mengurutkan lintas halaman, bukan hanya di dalam halaman', function () {
    /*
     * Inilah yang paling mudah salah saat memaginasi: mengurutkan SESUDAH
     * mengambil halaman hanya mengurutkan 25 baris itu, sehingga "halaman 2"
     * memuat kode yang seharusnya ada di halaman 5. Pengurutannya harus di SQL.
     */
    katalogUji($this->prodiId, $this->kurikulum, $this->term->id, 60);

    $halaman1 = $this->actingAs($this->mahasiswa, 'mahasiswa')
        ->get('/mahasiswa/krs')->viewData('katalog');

    $halaman3 = $this->actingAs($this->mahasiswa, 'mahasiswa')
        ->get('/mahasiswa/krs?page=3')->viewData('katalog');

    $kode = fn ($k) => collect($k->items())->map(fn (array $b): string => $b['kelas']->mataKuliah->kode);

    expect($kode($halaman1)->first())->toBe('KAT001')
        ->and($kode($halaman1)->last())->toBe('KAT025')
        ->and($kode($halaman3)->first())->toBe('KAT051')
        ->and($kode($halaman3)->last())->toBe('KAT060');
});

it('mencari berdasarkan kode maupun nama mata kuliah', function () {
    katalogUji($this->prodiId, $this->kurikulum, $this->term->id, 60);

    $perKode = $this->actingAs($this->mahasiswa, 'mahasiswa')
        ->get('/mahasiswa/krs?cari=KAT042')->viewData('katalog');

    $perNama = $this->actingAs($this->mahasiswa, 'mahasiswa')
        ->get('/mahasiswa/krs?cari=Uji 042')->viewData('katalog');

    expect($perKode->total())->toBe(1)
        ->and($perKode->items()[0]['kelas']->mataKuliah->kode)->toBe('KAT042')
        ->and($perNama->total())->toBe(1);
});

it('membawa kata pencarian ikut ke halaman berikutnya', function () {
    // Filter yang hilang saat berpindah halaman membuat hasil pencarian
    // mustahil ditelusuri lebih dari 25 baris pertama.
    katalogUji($this->prodiId, $this->kurikulum, $this->term->id, 60);

    $katalog = $this->actingAs($this->mahasiswa, 'mahasiswa')
        ->get('/mahasiswa/krs?cari=Mata Kuliah Uji')->viewData('katalog');

    expect($katalog->total())->toBe(60)
        ->and($katalog->nextPageUrl())->toContain('cari=');
});

it('mengatakan pencariannya yang kosong, bukan katalognya', function () {
    /*
     * Dua keadaan kosong yang berbeda. "Tidak ada yang cocok" dapat
     * ditindaklanjuti mahasiswa; "belum dibuka akademik" tidak. Menyamakan
     * keduanya membuat mahasiswa menunggu sesuatu yang tidak akan datang.
     */
    katalogUji($this->prodiId, $this->kurikulum, $this->term->id, 5);

    $this->actingAs($this->mahasiswa, 'mahasiswa')
        ->get('/mahasiswa/krs?cari=tidakadasamasekali')
        ->assertOk()
        ->assertSee('Tidak ada yang cocok')
        ->assertDontSee('Belum ada kelas ditawarkan');
});

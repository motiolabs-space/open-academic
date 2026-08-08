<?php

declare(strict_types=1);

use App\Enums\GradeLetter;
use App\Enums\KrsStatus;
use App\Enums\SemesterType;
use App\Exceptions\AturanAkademikException;
use App\Models\Akademik\KelasKuliah;
use App\Models\Akademik\Konsentrasi;
use App\Models\Akademik\Krs;
use App\Models\Akademik\KrsDetail;
use App\Models\Akademik\Kurikulum;
use App\Models\Akademik\MataKuliah;
use App\Models\Akademik\Nilai;
use App\Models\Akademik\PaketKuliah;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Services\Akademik\KrsService;
use App\Services\Akademik\PadananMataKuliah;
use App\Services\Akademik\PaketKuliahService;
use App\Services\Akademik\PrasyaratChecker;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->term = TahunAkademik::factory()->term(2026, SemesterType::Ganjil)->aktif()->create([
        'krs_mulai' => now()->subWeek(),
        'krs_selesai' => now()->addWeek(),
    ]);

    $this->lampau = TahunAkademik::factory()->term(2025, SemesterType::Ganjil)->terkunci()->create();

    $this->prodi = Prodi::factory()->create();
    $this->kurikulum = Kurikulum::factory()->create(['prodi_id' => $this->prodi->id]);

    $this->padanan = app(PadananMataKuliah::class);
    $this->krsService = app(KrsService::class);
});

function mkUji(string $kode, int $sks = 3): MataKuliah
{
    return MataKuliah::factory()->create([
        'prodi_id' => test()->prodi->id,
        'kode' => $kode,
        'sks' => $sks,
    ]);
}

/** Adds a course to the test curriculum, optionally restricted to a track. */
function keKurikulum(MataKuliah $mk, ?Konsentrasi $konsentrasi = null, int $semester = 1): void
{
    DB::table('kurikulum_mata_kuliah')->insert([
        'kurikulum_id' => test()->kurikulum->id,
        'mata_kuliah_id' => $mk->id,
        'konsentrasi_id' => $konsentrasi?->id,
        'semester' => $semester,
        'jenis' => 'wajib',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function mahasiswaUji(?Konsentrasi $konsentrasi = null): Mahasiswa
{
    $mahasiswa = Mahasiswa::factory()->create([
        'prodi_id' => test()->prodi->id,
        'kurikulum_id' => test()->kurikulum->id,
        'konsentrasi_id' => $konsentrasi?->id,
    ]);

    // Kepemilikan saja cukup untuk membaca KRS sendiri, tapi tidak untuk
    // mengubahnya — tanpa peran ini rute paket menjawab 403.
    $mahasiswa->assignRole('mahasiswa');

    return $mahasiswa;
}

/** Records a finalised pass for a student on a course, in the closed term. */
function lulusUji(Mahasiswa $mahasiswa, MataKuliah $mk): void
{
    $kelas = KelasKuliah::factory()->create([
        'tahun_akademik_id' => test()->lampau->id,
        'prodi_id' => test()->prodi->id,
        'mata_kuliah_id' => $mk->id,
        'sks' => $mk->sks,
    ]);

    // A grade hangs off an enrolment row, not off a student directly — the
    // schema keeps them together so a mark can never exist for a course
    // nobody took.
    $krs = Krs::create([
        'mahasiswa_id' => $mahasiswa->id,
        'tahun_akademik_id' => test()->lampau->id,
        'semester_ke' => 1,
        'status' => KrsStatus::Disetujui,
        'total_sks' => $mk->sks,
        'batas_sks' => 24,
    ]);

    $detail = KrsDetail::create([
        'krs_id' => $krs->id,
        'kelas_kuliah_id' => $kelas->id,
        'sks' => $mk->sks,
    ]);

    Nilai::create([
        'krs_detail_id' => $detail->id,
        'mahasiswa_id' => $mahasiswa->id,
        'kelas_kuliah_id' => $kelas->id,
        'nilai_angka' => 80,
        'nilai_huruf' => GradeLetter::A,
        'bobot' => GradeLetter::A->weight(),
        'is_final' => true,
    ]);
}

function kelasKurikulumUji(MataKuliah $mk): KelasKuliah
{
    return KelasKuliah::factory()->create([
        'tahun_akademik_id' => test()->term->id,
        'prodi_id' => test()->prodi->id,
        'mata_kuliah_id' => $mk->id,
        'sks' => $mk->sks,
        'kuota' => 40,
        'terisi' => 0,
    ]);
}

describe('padanan mata kuliah', function () {
    it('mengakui mata kuliah lama sebagai penggantinya', function () {
        /*
         * Persoalan yang muncul di setiap pergantian kurikulum: mahasiswa yang
         * sudah lulus "Algoritma & Pemrograman" tidak boleh disuruh mengambil
         * "Dasar Pemrograman".
         */
        $lama = mkUji('IF101');
        $baru = mkUji('IF111');

        $mahasiswa = mahasiswaUji();
        lulusUji($mahasiswa, $lama);

        $checker = app(PrasyaratChecker::class);

        expect($checker->sudahLulus($mahasiswa, $baru))->toBeFalse();

        $this->padanan->tetapkan($lama, $baru);
        $checker->lupakan();

        expect($checker->sudahLulus($mahasiswa, $baru))->toBeTrue();
    });

    it('tidak berlaku terbalik', function () {
        /*
         * Arah itu mengikat. Mata kuliah pengganti boleh mencakup lebih banyak,
         * dan menerimanya mundur akan meloloskan mahasiswa sekarang dari
         * prasyarat yang silabus lama tidak pernah ajarkan.
         */
        $lama = mkUji('IF101');
        $baru = mkUji('IF111');

        $this->padanan->tetapkan($lama, $baru);

        $mahasiswa = mahasiswaUji();
        lulusUji($mahasiswa, $baru);

        expect(app(PrasyaratChecker::class)->sudahLulus($mahasiswa, $lama))->toBeFalse();
    });

    it('mengikuti rantai padanan lintas beberapa kurikulum', function () {
        // 2018 → 2022 → 2026 adalah bentuk yang biasa; satu lompatan saja akan
        // meninggalkan mahasiswa angkatan lama.
        $v2018 = mkUji('IF101');
        $v2022 = mkUji('IF111');
        $v2026 = mkUji('IF121');

        $this->padanan->tetapkan($v2018, $v2022);
        $this->padanan->tetapkan($v2022, $v2026);

        $mahasiswa = mahasiswaUji();
        lulusUji($mahasiswa, $v2018);

        expect(app(PrasyaratChecker::class)->sudahLulus($mahasiswa, $v2026))->toBeTrue();
    });

    it('menolak lingkaran padanan', function () {
        /*
         * Lingkaran membuat setiap mata kuliah di dalamnya setara dengan semua
         * yang lain — hampir tidak pernah yang dimaksud, dan mustahil terlihat
         * begitu terbentuk.
         */
        $a = mkUji('IF101');
        $b = mkUji('IF111');
        $c = mkUji('IF121');

        $this->padanan->tetapkan($a, $b);
        $this->padanan->tetapkan($b, $c);

        expect(fn () => $this->padanan->tetapkan($c, $a))
            ->toThrow(AturanAkademikException::class, 'membentuk lingkaran');
    });

    it('menolak padanan ke dirinya sendiri', function () {
        $a = mkUji('IF101');

        expect(fn () => $this->padanan->tetapkan($a, $a))
            ->toThrow(AturanAkademikException::class, 'dirinya sendiri');
    });

    it('memenuhi prasyarat lewat padanan', function () {
        // Bukan hanya "sudah lulus" — prasyarat membaca himpunan yang sama,
        // karena keduanya lewat satu tempat.
        $lama = mkUji('IF101');
        $baru = mkUji('IF111');
        $lanjut = mkUji('IF201');

        $lanjut->prasyarat()->attach($baru->id, ['jenis' => 'prasyarat']);

        $mahasiswa = mahasiswaUji();
        lulusUji($mahasiswa, $lama);

        $this->padanan->tetapkan($lama, $baru);

        expect(app(PrasyaratChecker::class)->terpenuhi($mahasiswa, $lanjut))->toBeTrue();
    });

    it('menolak mengambil kelas yang mata kuliahnya sudah dilulusi lewat padanan', function () {
        $lama = mkUji('IF101');
        $baru = mkUji('IF111');
        keKurikulum($baru);

        $mahasiswa = mahasiswaUji();
        lulusUji($mahasiswa, $lama);
        $this->padanan->tetapkan($lama, $baru);

        $krs = $this->krsService->bukaAtauAmbil($mahasiswa, $this->term);

        expect(fn () => $this->krsService->tambahKelas($krs, kelasKurikulumUji($baru)))
            ->toThrow(AturanAkademikException::class);
    });
});

describe('kurikulum konsentrasi', function () {
    beforeEach(function () {
        $this->jalurA = Konsentrasi::create([
            'kurikulum_id' => $this->kurikulum->id,
            'kode' => 'RPL',
            'nama' => 'Rekayasa Perangkat Lunak',
        ]);

        $this->jalurB = Konsentrasi::create([
            'kurikulum_id' => $this->kurikulum->id,
            'kode' => 'JAR',
            'nama' => 'Jaringan',
        ]);
    });

    it('mengizinkan mata kuliah bersama untuk semua mahasiswa', function () {
        // Sebagian besar gelar itu bersama; null berarti "tanpa batasan jalur",
        // bukan "belum memilih jalur".
        $bersama = mkUji('UM101');
        keKurikulum($bersama);

        $mahasiswa = mahasiswaUji();
        $krs = $this->krsService->bukaAtauAmbil($mahasiswa, $this->term);

        $detail = $this->krsService->tambahKelas($krs, kelasKurikulumUji($bersama));

        expect($detail->exists)->toBeTrue();
    });

    it('menolak mata kuliah jalur lain', function () {
        $khususA = mkUji('RPL201');
        keKurikulum($khususA, $this->jalurA);

        $mahasiswa = mahasiswaUji($this->jalurB);
        $krs = $this->krsService->bukaAtauAmbil($mahasiswa, $this->term);

        expect(fn () => $this->krsService->tambahKelas($krs, kelasKurikulumUji($khususA)))
            ->toThrow(AturanAkademikException::class);
    });

    it('menolak mahasiswa yang belum memilih jalur', function () {
        /*
         * Ditolak, bukan diloloskan. Meloloskannya berarti ia menempuh mata
         * kuliah yang dihitung ke syarat jalur yang tidak berlaku baginya — dan
         * menemukan itu saat yudisium jauh lebih mahal daripada diberi tahu
         * sekarang.
         */
        $khususA = mkUji('RPL201');
        keKurikulum($khususA, $this->jalurA);

        $mahasiswa = mahasiswaUji();
        $krs = $this->krsService->bukaAtauAmbil($mahasiswa, $this->term);

        expect(fn () => $this->krsService->tambahKelas($krs, kelasKurikulumUji($khususA)))
            ->toThrow(AturanAkademikException::class);
    });

    it('mengizinkan mata kuliah jalur sendiri', function () {
        $khususA = mkUji('RPL201');
        keKurikulum($khususA, $this->jalurA);

        $mahasiswa = mahasiswaUji($this->jalurA);
        $krs = $this->krsService->bukaAtauAmbil($mahasiswa, $this->term);

        expect($this->krsService->tambahKelas($krs, kelasKurikulumUji($khususA))->exists)->toBeTrue();
    });

    it('melihat mata kuliah yang baru ditambahkan ke kurikulum', function () {
        /*
         * Versi pertama gerbang ini memoisasi peta kurikulum pada objek
         * servicenya. Cepat, dan basi begitu ada mata kuliah baru ditambahkan
         * dalam proses yang sama — dua tes KRS yang sudah ada langsung gagal,
         * dan sebuah queue worker atau proses Octane akan rusak dengan cara yang
         * sama tanpa ada yang menyadarinya.
         */
        $mahasiswa = mahasiswaUji();
        $pertama = mkUji('AWAL101');
        keKurikulum($pertama);

        $krs = $this->krsService->bukaAtauAmbil($mahasiswa, $this->term);
        $this->krsService->tambahKelas($krs, kelasKurikulumUji($pertama));

        // Ditambahkan ke kurikulum sesudah pemeriksaan pertama berjalan.
        $kemudian = mkUji('SUSUL101');
        keKurikulum($kemudian);

        expect($this->krsService->tambahKelas($krs->refresh(), kelasKurikulumUji($kemudian))->exists)
            ->toBeTrue();
    });

    /*
     * Katalog KRS memfilter kurikulum lewat SQL-nya sendiri dan tidak tahu apa
     * pun tentang konsentrasi. Tanpa tes ini, mata kuliah jalur lain tampil
     * dengan tombol "Ambil" yang hidup lalu ditolak saat ditekan — persis
     * kegagalan yang layar itu ada untuk mencegahnya.
     */
    it('tidak menawarkan tombol ambil untuk mata kuliah jalur lain', function () {
        $khususA = mkUji('RPL201');
        keKurikulum($khususA, $this->jalurA);
        $kelas = kelasKurikulumUji($khususA);

        $mahasiswa = mahasiswaUji($this->jalurB);

        $this->actingAs($mahasiswa, 'mahasiswa')
            ->get('/mahasiswa/krs')
            ->assertOk()
            ->assertSee('Luar konsentrasi')
            ->assertDontSee(route('mahasiswa.krs.tambah', $kelas), false);
    });

    it('memberi tahu mahasiswa tanpa jalur apa yang harus dilakukan', function () {
        // Dua sebab yang tampak sama di layar, tapi hanya satu yang dapat
        // ditindaklanjuti mahasiswa.
        $khususA = mkUji('RPL201');
        keKurikulum($khususA, $this->jalurA);
        kelasKurikulumUji($khususA);

        $this->actingAs(mahasiswaUji(), 'mahasiswa')
            ->get('/mahasiswa/krs')
            ->assertOk()
            ->assertSee('Tetapkan konsentrasi');
    });
});

describe('kuliah paket', function () {
    beforeEach(function () {
        $this->paketService = app(PaketKuliahService::class);

        $this->mkA = mkUji('PK101', 3);
        $this->mkB = mkUji('PK102', 2);

        keKurikulum($this->mkA);
        keKurikulum($this->mkB);

        $this->paket = PaketKuliah::create([
            'kurikulum_id' => $this->kurikulum->id,
            'semester_ke' => 1,
            'nama' => 'Paket Semester 1',
        ]);

        $this->paket->mataKuliah()->attach([$this->mkA->id, $this->mkB->id]);
    });

    it('mengisi rencana studi dari paket', function () {
        $mahasiswa = mahasiswaUji();
        kelasKurikulumUji($this->mkA);
        kelasKurikulumUji($this->mkB);

        $krs = $this->krsService->bukaAtauAmbil($mahasiswa, $this->term);
        $hasil = $this->paketService->terapkan($krs);

        expect($hasil['ditambahkan'])->toBe(2)
            ->and($krs->refresh()->total_sks)->toBe(5);
    });

    it('tetap menegakkan aturan yang sama seperti KRS pilihan', function () {
        /*
         * Paket mengubah siapa yang memilih, bukan aturan mana yang berlaku.
         * Menulis krs_detail langsung akan melewati kunci kuota, deteksi
         * bentrok, prasyarat, dan penjaga hitung-ganda — diam-diam, untuk satu
         * angkatan sekaligus.
         */
        $mahasiswa = mahasiswaUji();
        lulusUji($mahasiswa, $this->mkA);

        kelasKurikulumUji($this->mkA);
        kelasKurikulumUji($this->mkB);

        $krs = $this->krsService->bukaAtauAmbil($mahasiswa, $this->term);
        $hasil = $this->paketService->terapkan($krs);

        expect($hasil['ditambahkan'])->toBe(1)
            ->and($hasil['dilewati'])->toHaveCount(1)
            ->and($hasil['dilewati'][0]['mata_kuliah'])->toBe($this->mkA->nama);
    });

    it('menyebutkan mata kuliah yang kelasnya belum dibuka', function () {
        // Satu angkatan yang paketnya separuh gagal butuh tahu bagian mana —
        // bukan sekadar cacah yang lebih kecil dari harapan.
        $mahasiswa = mahasiswaUji();
        kelasKurikulumUji($this->mkA);

        $krs = $this->krsService->bukaAtauAmbil($mahasiswa, $this->term);
        $hasil = $this->paketService->terapkan($krs);

        expect($hasil['ditambahkan'])->toBe(1)
            ->and($hasil['dilewati'][0]['alasan'])->toContain('Belum ada kelas');
    });

    it('mendahulukan paket jalur atas paket bersama', function () {
        $jalur = Konsentrasi::create([
            'kurikulum_id' => $this->kurikulum->id,
            'kode' => 'RPL',
            'nama' => 'RPL',
        ]);

        $paketJalur = PaketKuliah::create([
            'kurikulum_id' => $this->kurikulum->id,
            'konsentrasi_id' => $jalur->id,
            'semester_ke' => 1,
            'nama' => 'Paket RPL Semester 1',
        ]);

        $dipilih = PaketKuliah::untuk($this->kurikulum->id, $jalur->id, 1);

        expect($dipilih?->id)->toBe($paketJalur->id);
    });

    it('menolak penerapan pada rencana studi yang sudah diajukan', function () {
        $mahasiswa = mahasiswaUji();
        $krs = $this->krsService->bukaAtauAmbil($mahasiswa, $this->term);
        $krs->update(['status' => KrsStatus::Diajukan]);

        expect(fn () => $this->paketService->terapkan($krs->refresh()))
            ->toThrow(AturanAkademikException::class, 'masih berstatus draf');
    });

    it('mengatakan dengan jelas ketika paketnya belum ada', function () {
        $mahasiswa = mahasiswaUji();
        $krs = $this->krsService->bukaAtauAmbil($mahasiswa, $this->term);
        $krs->update(['semester_ke' => 7]);

        expect(fn () => $this->paketService->terapkan($krs->refresh()))
            ->toThrow(AturanAkademikException::class, 'Belum ada paket kuliah untuk semester 7');
    });

    /*
     * Servicenya sudah lengkap dan teruji sejak awal, tapi tidak ada satu pun
     * pemanggilnya di aplikasi — mahasiswa di prodi berpaket tetap harus memilih
     * satu per satu. Fitur yang hanya dapat dijalankan oleh tes bukan fitur.
     */
    it('menawarkan penerapan paket di layar KRS prodi berpaket', function () {
        test()->prodi->update(['mode_krs' => 'paket']);
        kelasKurikulumUji($this->mkA);
        kelasKurikulumUji($this->mkB);

        $this->actingAs(mahasiswaUji(), 'mahasiswa')
            ->get('/mahasiswa/krs')
            ->assertOk()
            ->assertSee('Paket Semester')
            ->assertSee('Terapkan paket');
    });

    it('tidak menawarkan apa pun di prodi yang mahasiswanya memilih sendiri', function () {
        kelasKurikulumUji($this->mkA);

        $this->actingAs(mahasiswaUji(), 'mahasiswa')
            ->get('/mahasiswa/krs')
            ->assertOk()
            ->assertDontSee('Terapkan paket');
    });

    it('menerapkan paket lewat layar dan menyebutkan yang dilewati', function () {
        test()->prodi->update(['mode_krs' => 'paket']);

        $mahasiswa = mahasiswaUji();
        lulusUji($mahasiswa, $this->mkA);

        kelasKurikulumUji($this->mkA);
        kelasKurikulumUji($this->mkB);

        $this->actingAs($mahasiswa, 'mahasiswa')
            ->post('/mahasiswa/krs/paket')
            ->assertRedirect()
            ->assertSessionHas('paket_dilewati');

        expect(Krs::where('mahasiswa_id', $mahasiswa->id)
            ->where('tahun_akademik_id', test()->term->id)
            ->first()->total_sks)->toBe($this->mkB->sks);
    });
});

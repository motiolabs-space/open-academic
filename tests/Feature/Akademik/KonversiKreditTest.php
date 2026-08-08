<?php

declare(strict_types=1);

use App\Enums\JenisKonversi;
use App\Enums\SemesterType;
use App\Enums\StatusKonversi;
use App\Enums\StudentStatus;
use App\Exceptions\AturanAkademikException;
use App\Models\Akademik\KelasKuliah;
use App\Models\Akademik\KonversiKredit;
use App\Models\Akademik\Krs;
use App\Models\Akademik\KrsDetail;
use App\Models\Akademik\MataKuliah;
use App\Models\Akademik\Nilai;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Staff;
use App\Services\Akademik\KonversiService;
use App\Services\Akademik\KrsService;
use App\Services\Akademik\PerolehanAkademik;
use App\Services\Akademik\TranskripService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\UniqueConstraintViolationException;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    // Jendela KRS dibuka: satu tes di bawah menempuh KrsService, dan gerbang
    // kalender akademik menyala lebih dulu daripada aturan yang diuji.
    $this->term = TahunAkademik::factory()->term(2026, SemesterType::Ganjil)->aktif()->create([
        'krs_mulai' => now()->subWeek(),
        'krs_selesai' => now()->addWeek(),
    ]);
    $this->prodi = Prodi::factory()->create(['sks_lulus' => 144]);

    $this->staf = Staff::factory()->create();
    $this->staf->assignRole('baak');

    $this->mahasiswa = Mahasiswa::factory()->create([
        'prodi_id' => $this->prodi->id,
        'status' => StudentStatus::Aktif,
    ]);

    $this->konversi = app(KonversiService::class);
    $this->perolehan = app(PerolehanAkademik::class);
});

function mkKonversi(int $sks = 3): MataKuliah
{
    return MataKuliah::factory()->create(['prodi_id' => test()->prodi->id, 'sks' => $sks]);
}

/** Nilai final di kampus ini, untuk menguji tabrakan dengan konversi. */
function nilaiDiSini(Mahasiswa $mahasiswa, MataKuliah $mk, string $huruf = 'A', float $bobot = 4.0): Nilai
{
    $kelas = KelasKuliah::factory()->create([
        'tahun_akademik_id' => test()->term->id,
        'mata_kuliah_id' => $mk->id,
        'prodi_id' => $mahasiswa->prodi_id,
        'sks' => $mk->sks,
    ]);

    $krs = Krs::firstOrCreate(
        ['mahasiswa_id' => $mahasiswa->id, 'tahun_akademik_id' => test()->term->id],
        ['semester_ke' => 1, 'batas_sks' => 99, 'status' => 'disetujui'],
    );

    $detail = KrsDetail::create(['krs_id' => $krs->id, 'kelas_kuliah_id' => $kelas->id, 'sks' => $mk->sks]);

    return Nilai::create([
        'krs_detail_id' => $detail->id,
        'kelas_kuliah_id' => $kelas->id,
        'mahasiswa_id' => $mahasiswa->id,
        'nilai_angka' => 85,
        'nilai_huruf' => $huruf,
        'bobot' => $bobot,
        'is_final' => true,
    ]);
}

/** Usulan konversi yang sudah disetujui. */
function konversiDisetujui(MataKuliah $mk, ?string $huruf = 'B', ?int $sks = null): KonversiKredit
{
    $usulan = test()->konversi->ajukan(
        test()->mahasiswa,
        $mk,
        JenisKonversi::Transfer,
        'Basis Data Lanjut',
        'Universitas Contoh',
        3,
        'A',
    );

    return test()->konversi->setujui($usulan, test()->staf, $sks, $huruf);
}

describe('pengakuan kredit', function () {
    it('mencatat SKS yang diakui dan menghitungnya ke total', function () {
        konversiDisetujui(mkKonversi(3));

        $angka = $this->perolehan->ringkasUntuk($this->mahasiswa);

        expect($angka['sksLulus'])->toBe(3)
            ->and($angka['sksKonversi'])->toBe(3);
    });

    it('mewajibkan institusi asal pada konversi transfer', function () {
        expect(fn () => $this->konversi->ajukan(
            $this->mahasiswa, mkKonversi(), JenisKonversi::Transfer, 'Basis Data', null,
        ))->toThrow(AturanAkademikException::class, 'perguruan tinggi asal');
    });

    it('tidak mewajibkan institusi pada RPL', function () {
        // Sumbernya sering pengalaman kerja, bukan kampus lain.
        expect(fn () => $this->konversi->ajukan(
            $this->mahasiswa, mkKonversi(), JenisKonversi::Rpl, 'Lima tahun sebagai administrator basis data',
        ))->not->toThrow(AturanAkademikException::class);
    });

    it('mengakui kredit tanpa huruf nilai', function () {
        // Lazim pada RPL: kampus memutuskan syaratnya terpenuhi tanpa memberi
        // nilai. Menganggapnya tidak lulus berarti memberi kredit lalu menolak
        // menghitungnya.
        $k = konversiDisetujui(mkKonversi(3), huruf: null);

        $baris = $this->perolehan->untuk($this->mahasiswa)->first();

        expect($k->nilai_huruf)->toBeNull()
            ->and($baris->lulus)->toBeTrue()
            ->and($this->perolehan->ringkasUntuk($this->mahasiswa)['sksLulus'])->toBe(3);
    });
});

describe('batas yang menjaga arti gelar', function () {
    it('menolak SKS diakui melebihi bobot mata kuliah di sini', function () {
        $mk = mkKonversi(3);
        $usulan = $this->konversi->ajukan(
            $this->mahasiswa, $mk, JenisKonversi::Transfer, 'MK Asal', 'Universitas Contoh', 6, 'A',
        );

        expect(fn () => $this->konversi->setujui($usulan, $this->staf, 6, 'A'))
            ->toThrow(AturanAkademikException::class, 'melebihi bobot mata kuliah');
    });

    it('menolak pengakuan yang melewati batas persentase', function () {
        // Tanpa batas, seseorang dapat "diakui" masuk ke dalam gelar.
        config(['academic.konversi.maks_persen' => 10]); // 14 SKS dari 144

        konversiDisetujui(mkKonversi(6), huruf: 'A');
        konversiDisetujui(mkKonversi(6), huruf: 'A');

        $ketiga = $this->konversi->ajukan(
            $this->mahasiswa, mkKonversi(6), JenisKonversi::Transfer, 'MK', 'Universitas Contoh',
        );

        expect(fn () => $this->konversi->setujui($ketiga, $this->staf, 6, 'A'))
            ->toThrow(AturanAkademikException::class, 'Melebihi batas pengakuan kredit');
    });

    it('melaporkan sisa kuota', function () {
        config(['academic.konversi.maks_persen' => 50]); // 72 SKS

        konversiDisetujui(mkKonversi(6), huruf: 'A');

        expect($this->konversi->batas($this->mahasiswa))->toBe(72)
            ->and($this->konversi->sudahDiakui($this->mahasiswa))->toBe(6)
            ->and($this->konversi->sisaKuota($this->mahasiswa))->toBe(66);
    });
});

describe('kredit ganda', function () {
    it('menolak mengonversi mata kuliah yang sudah ditempuh di sini', function () {
        $mk = mkKonversi(3);
        nilaiDiSini($this->mahasiswa, $mk);

        expect(fn () => $this->konversi->ajukan(
            $this->mahasiswa, $mk, JenisKonversi::Transfer, 'MK', 'Universitas Contoh',
        ))->toThrow(AturanAkademikException::class, 'sudah ditempuh dan dinilai di kampus ini');
    });

    it('menolak usulan kedua untuk mata kuliah yang sama', function () {
        $mk = mkKonversi(3);
        konversiDisetujui($mk);

        expect(fn () => $this->konversi->ajukan(
            $this->mahasiswa, $mk, JenisKonversi::Transfer, 'MK', 'Universitas Contoh',
        ))->toThrow(AturanAkademikException::class, 'Sudah ada usulan atau konversi berjalan');
    });

    it('dijaga basis data, bukan hanya oleh service', function () {
        // Penjaga di service kalah oleh dua permintaan bersamaan.
        $mk = mkKonversi(3);
        konversiDisetujui($mk);

        expect(fn () => KonversiKredit::create([
            'mahasiswa_id' => $this->mahasiswa->id,
            'mata_kuliah_id' => $mk->id,
            'jenis' => JenisKonversi::Transfer,
            'status' => StatusKonversi::Disetujui,
            'asal_nama' => 'Tembus',
            'sks_diakui' => 3,
            'kunci_aktif' => KonversiKredit::kunci($this->mahasiswa->id, $mk->id),
        ]))->toThrow(UniqueConstraintViolationException::class);
    });

    it('menolak mengambil kelas dari mata kuliah yang sudah dikonversi', function () {
        /*
         * Separuh lain dari aturan kredit ganda. KonversiService menolak
         * mengonversi mata kuliah yang sudah ditempuh; ini menolak menempuh
         * yang sudah dikonversi.
         *
         * Tanpa keduanya, mahasiswa pindahan yang sudah diakui Basis Data tetap
         * bisa mengambilnya dan lulus dengan kreditnya terhitung dua kali — dan
         * tidak ada yang menyadarinya, karena totalnya hanya keluar lebih besar
         * daripada mata kuliah di belakangnya.
         */
        $mk = mkKonversi(3);
        konversiDisetujui($mk);

        $kelas = KelasKuliah::factory()->create([
            'tahun_akademik_id' => $this->term->id,
            'mata_kuliah_id' => $mk->id,
            'prodi_id' => $this->prodi->id,
            'sks' => 3,
        ]);

        $krs = Krs::create([
            'mahasiswa_id' => $this->mahasiswa->id,
            'tahun_akademik_id' => $this->term->id,
            'semester_ke' => 3,
            'batas_sks' => 24,
            'status' => 'draft',
        ]);

        expect(fn () => app(KrsService::class)->tambahKelas($krs, $kelas))
            ->toThrow(AturanAkademikException::class, 'sudah diakui lewat konversi kredit');
    });

    it('membebaskan mata kuliahnya setelah konversi dicabut', function () {
        $mk = mkKonversi(3);
        $k = konversiDisetujui($mk);

        $this->konversi->cabut($k, $this->staf, 'Transkrip asal tidak dapat diverifikasi.');

        expect(fn () => $this->konversi->ajukan(
            $this->mahasiswa, $mk, JenisKonversi::Transfer, 'MK', 'Universitas Contoh',
        ))->not->toThrow(AturanAkademikException::class);
    });

    it('menolak mencabut konversi milik mahasiswa yang sudah lulus', function () {
        // Kreditnya sudah masuk total yang tercetak di transkrip dan dikutip
        // pada ijazah.
        $k = konversiDisetujui(mkKonversi(3));
        $this->mahasiswa->update(['status' => StudentStatus::Lulus]);

        expect(fn () => $this->konversi->cabut($k->fresh(), $this->staf, 'Keliru.'))
            ->toThrow(AturanAkademikException::class, 'sudah lulus');
    });
});

describe('IPK', function () {
    it('tidak memasukkan nilai konversi ke IPK secara bawaan', function () {
        /*
         * IPK adalah penilaian institusi ini. Nilai yang dikonversi diberikan
         * pihak lain dengan standar lain.
         */
        nilaiDiSini($this->mahasiswa, mkKonversi(3), 'A', 4.0);
        konversiDisetujui(mkKonversi(3), huruf: 'C');

        $angka = $this->perolehan->ringkasUntuk($this->mahasiswa);

        expect($angka['ipk'])->toBe(4.0)
            ->and($angka['sksLulus'])->toBe(6);
    });

    it('memasukkannya bila kampus memilih demikian', function () {
        config(['academic.konversi.hitung_ipk' => true]);

        nilaiDiSini($this->mahasiswa, mkKonversi(3), 'A', 4.0);
        konversiDisetujui(mkKonversi(3), huruf: 'C');

        // (4,0 × 3 + 2,0 × 3) ÷ 6
        expect($this->perolehan->ringkasUntuk($this->mahasiswa)['ipk'])->toBe(3.0);
    });

    it('tidak menyisakan konversi di penyebut saat dikeluarkan dari pembilang', function () {
        // Kalau ikut di bawah tetapi tidak di atas, setiap mahasiswa pindahan
        // IPK-nya tertekan diam-diam.
        config(['academic.konversi.hitung_ipk' => false]);

        nilaiDiSini($this->mahasiswa, mkKonversi(3), 'A', 4.0);
        konversiDisetujui(mkKonversi(3), huruf: 'A');

        expect($this->perolehan->ringkasUntuk($this->mahasiswa)['ipk'])->toBe(4.0);
    });
});

describe('layar admin', function () {
    it('merender pilihan mahasiswa sebelum ada yang dipilih', function () {
        // Keputusannya per mahasiswa, bukan per baris: penilai memetakan satu
        // transkrip asal sekaligus, dengan satu batas untuk seluruh isinya.
        $this->actingAs($this->staf, 'staff')
            ->get(route('admin.konversi'))
            ->assertOk()
            ->assertSee('Pilih mahasiswa');
    });

    it('menampilkan batas dan sisa kuota sebelum memutuskan', function () {
        konversiDisetujui(mkKonversi(6), huruf: 'A');

        $this->actingAs($this->staf, 'staff')
            ->get(route('admin.konversi', ['mahasiswa' => $this->mahasiswa->uuid]))
            ->assertOk()
            // x-stat-card mencetak labelnya dalam huruf besar.
            ->assertSee('SISA')
            ->assertSee('BATAS PENGAKUAN')
            ->assertSee($this->mahasiswa->nim);
    });

    it('mengakui usulan lewat layar', function () {
        $mk = mkKonversi(3);
        $usulan = $this->konversi->ajukan(
            $this->mahasiswa, $mk, JenisKonversi::Transfer, 'MK Asal', 'Universitas Contoh',
        );

        $this->actingAs($this->staf, 'staff')
            ->post(route('admin.konversi.setujui', $usulan), ['sks_diakui' => 3, 'nilai_huruf' => 'B'])
            ->assertRedirect();

        expect($usulan->fresh()->status)->toBe(StatusKonversi::Disetujui);
    });

    it('menolak staf tanpa izin kemahasiswaan', function () {
        $keuangan = Staff::factory()->create();
        $keuangan->assignRole('operator-pddikti');

        $this->actingAs($keuangan, 'staff')
            ->get(route('admin.konversi'))
            ->assertOk();

        $usulan = $this->konversi->ajukan(
            $this->mahasiswa, mkKonversi(), JenisKonversi::Transfer, 'MK', 'Universitas Contoh',
        );

        // Boleh melihat, tidak boleh memutuskan.
        $this->actingAs($keuangan, 'staff')
            ->post(route('admin.konversi.setujui', $usulan), ['sks_diakui' => 3])
            ->assertForbidden();
    });
});

describe('transkrip', function () {
    it('menampilkan baris konversi dengan penanda dan mencantumkan totalnya', function () {
        konversiDisetujui(mkKonversi(3));

        $data = app(TranskripService::class)->data($this->mahasiswa->fresh());

        expect($data['adaKonversi'])->toBeTrue()
            ->and($data['sksKonversi'])->toBe(3)
            ->and(collect($data['perSemester'])->flatten()->first()->tanda)->toBe('T');
    });

    it('mengelompokkan konversi di bawah institusi asalnya', function () {
        konversiDisetujui(mkKonversi(3));

        $data = app(TranskripService::class)->data($this->mahasiswa->fresh());

        expect(array_keys($data['perSemester']->all()))->toContain('Universitas Contoh');
    });
});

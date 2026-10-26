<?php

declare(strict_types=1);

use App\DTOs\Kemahasiswaan\AsalPendidikanData;
use App\DTOs\Kemahasiswaan\BarisTranskripData;
use App\DTOs\Kemahasiswaan\IdentitasMahasiswaData;
use App\Enums\ApplicantStatus;
use App\Enums\JenisDaftar;
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
use App\Models\Feeder\FeederMapping;
use App\Models\Feeder\FeederRef;
use App\Models\Kemahasiswaan\EvaluasiStudi;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Kemahasiswaan\StatusMahasiswa;
use App\Models\Pmb\PmbGelombang;
use App\Models\Pmb\PmbPendaftar;
use App\Models\Sdm\Staff;
use App\Services\Akademik\BatasSksCalculator;
use App\Services\Akademik\KonversiService;
use App\Services\Akademik\KrsService;
use App\Services\Feeder\FeederValidator;
use App\Services\Kemahasiswaan\EvaluasiStudiService;
use App\Services\Kemahasiswaan\PindahanService;
use Database\Seeders\RolePermissionSeeder;
use Spatie\Permission\Models\Role;

/**
 * Intake mahasiswa pindahan & RPL (#33).
 *
 * The property everything here leans on: `mahasiswa.sks_diakui` is never
 * typed. It is the sum of approved conversions, and the screens, the KRS
 * ceiling, the entry semester and the Feeder payload all read that one figure.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->term = TahunAkademik::factory()->term(2026, SemesterType::Ganjil)->aktif()->krsDibuka()->create();
    $this->prodi = Prodi::factory()->create(['kode' => 'IF', 'kode_pddikti' => '55201', 'sks_lulus' => 144]);

    $this->staf = Staff::factory()->create();
    $this->staf->assignRole('baak');

    $this->pindahan = app(PindahanService::class);
    $this->konversi = app(KonversiService::class);
});

function asalPindahan(array $atribut = []): AsalPendidikanData
{
    return AsalPendidikanData::fromArray([
        'jenis_daftar' => JenisDaftar::Pindahan->value,
        'pt_asal' => 'Universitas Merdeka Nusantara',
        'prodi_asal' => 'Teknik Informatika',
        'term_masuk_asal' => '20241',
        ...$atribut,
    ]);
}

function identitasPindahan(array $atribut = []): IdentitasMahasiswaData
{
    return IdentitasMahasiswaData::fromArray([
        'nama' => 'Nadia Paramitha',
        'email' => fake()->unique()->safeEmail(),
        'nik' => '3273015507040002',
        'tempat_lahir' => 'Bandung',
        'tanggal_lahir' => '2004-07-15',
        'jenis_kelamin' => 'P',
        ...$atribut,
    ]);
}

function pindahanBaru(array $asal = []): Mahasiswa
{
    return test()->pindahan->daftarkanLangsung(
        identitasPindahan(),
        test()->prodi,
        test()->term,
        asalPindahan($asal),
    )['mahasiswa'];
}

function mkPindahan(int $sks = 3): MataKuliah
{
    return MataKuliah::factory()->create(['prodi_id' => test()->prodi->id, 'sks' => $sks]);
}

/** Proposes one transcript line and approves it with a grade. */
function akuiPindahan(Mahasiswa $mahasiswa, MataKuliah $mk, ?string $huruf = 'A'): KonversiKredit
{
    $usulan = test()->pindahan->ajukanTranskrip($mahasiswa, [
        new BarisTranskripData($mk->id, 'Asal '.$mk->nama, (int) $mk->sks, $huruf),
    ])->first();

    return test()->konversi->setujui($usulan, test()->staf, null, $huruf);
}

/** The intake form as BAAK would submit it for a direct registration. */
function formIntake(array $atribut = []): array
{
    return [
        'nama' => 'Rizky Hidayat',
        'email' => 'rizky.pindahan@contoh.test',
        'nik' => '3273011203030001',
        'tempat_lahir' => 'Bandung',
        'tanggal_lahir' => '2003-03-12',
        'jenis_kelamin' => 'L',
        'prodi_id' => test()->prodi->id,
        'tahun_akademik_id' => test()->term->id,
        'jenis_daftar' => JenisDaftar::Pindahan->value,
        'pt_asal' => 'Universitas Merdeka Nusantara',
        'prodi_asal' => 'Teknik Informatika',
        'term_masuk_asal' => '20241',
        ...$atribut,
    ];
}

function gelombangTransfer(): PmbGelombang
{
    return PmbGelombang::create([
        'tahun_akademik_id' => test()->term->id,
        'kode' => 'PMB-TR-'.fake()->unique()->numerify('###'),
        'nama' => 'Gelombang Pindahan',
        'jalur' => 'transfer',
        'tanggal_mulai' => now()->subMonth(),
        'tanggal_selesai' => now()->addMonth(),
        'biaya_pendaftaran' => 0,
        'is_active' => true,
    ]);
}

function pendaftarTransfer(array $atribut = []): PmbPendaftar
{
    return PmbPendaftar::create([
        'pmb_gelombang_id' => gelombangTransfer()->id,
        'nomor_pendaftaran' => 'TR-'.fake()->unique()->numerify('######'),
        'nama' => 'Bagas Wicaksono',
        'email' => fake()->unique()->safeEmail(),
        'nik' => '3374021203030004',
        'tempat_lahir' => 'Semarang',
        'tanggal_lahir' => '2003-03-12',
        'jenis_kelamin' => 'L',
        'prodi_pilihan_1_id' => test()->prodi->id,
        'prodi_diterima_id' => test()->prodi->id,
        'status' => ApplicantStatus::Lulus,
        ...$atribut,
    ]);
}

describe('pintu pendaftaran langsung', function () {
    it('membuat mahasiswa pindahan lengkap dengan asal, NIM, dan periode masuk', function () {
        $hasil = $this->pindahan->daftarkanLangsung(identitasPindahan(), $this->prodi, $this->term, asalPindahan());
        $m = $hasil['mahasiswa']->fresh();

        expect($m->jenis_daftar)->toBe(JenisDaftar::Pindahan)
            ->and($m->pt_asal)->toBe('Universitas Merdeka Nusantara')
            ->and($m->prodi_asal)->toBe('Teknik Informatika')
            ->and($m->term_masuk_asal)->toBe('20241')
            ->and($m->term_masuk)->toBe($this->term->kode)
            ->and($m->angkatan)->toBe(2026)
            ->and($m->nim)->toStartWith('2655201')
            ->and($m->status)->toBe(StudentStatus::Aktif)
            ->and($m->hasRole('mahasiswa'))->toBeTrue()
            ->and($hasil['kata_sandi'])->not->toBeEmpty();

        // Belum ada konversi yang disetujui: tidak ada angka yang dikarang.
        expect($m->sks_diakui)->toBeNull();
    });

    it('menolak jenis daftar yang tidak melalui intake pindahan', function () {
        $this->pindahan->daftarkanLangsung(
            identitasPindahan(),
            $this->prodi,
            $this->term,
            AsalPendidikanData::fromArray(['jenis_daftar' => JenisDaftar::Baru->value]),
        );
    })->throws(AturanAkademikException::class, 'tidak melalui intake pindahan');

    it('menolak pindahan tanpa program studi asal', function () {
        pindahanBaru(['prodi_asal' => '  ']);
    })->throws(AturanAkademikException::class, 'program studi asal');

    it('membuang asal yang diisi untuk peserta didik baru', function () {
        $asal = AsalPendidikanData::fromArray([
            'jenis_daftar' => JenisDaftar::Baru->value,
            'pt_asal' => 'Universitas Lain',
            'prodi_asal' => 'Manajemen',
        ]);

        expect($asal->ptAsal)->toBeNull()->and($asal->prodiAsal)->toBeNull();
    });
});

describe('pintu PMB', function () {
    it('meregistrasi pendaftar jalur transfer dan mencatat asalnya dalam satu langkah', function () {
        $pendaftar = pendaftarTransfer();

        $hasil = $this->pindahan->daftarkanDariPmb($pendaftar, $this->term, asalPindahan());
        $m = $hasil['mahasiswa'];

        expect($m->jenis_daftar)->toBe(JenisDaftar::Pindahan)
            ->and($m->pt_asal)->toBe('Universitas Merdeka Nusantara')
            ->and($m->term_masuk)->toBe($this->term->kode)
            ->and($m->nik)->toBe('3374021203030004')
            ->and($pendaftar->fresh()->status)->toBe(ApplicantStatus::Mahasiswa)
            ->and($pendaftar->fresh()->mahasiswa_id)->toBe($m->id);
    });

    it('tidak meninggalkan mahasiswa setengah jadi bila asalnya ditolak', function () {
        $pendaftar = pendaftarTransfer();

        expect(fn () => $this->pindahan->daftarkanDariPmb($pendaftar, $this->term, asalPindahan(['pt_asal' => ''])))
            ->toThrow(AturanAkademikException::class);

        expect(Mahasiswa::count())->toBe(0)
            ->and($pendaftar->fresh()->status)->toBe(ApplicantStatus::Lulus);
    });

    it('mengarahkan pendaftar gelombang transfer ke intake, bukan daftar ulang biasa', function () {
        $pendaftar = pendaftarTransfer();

        $this->actingAs($this->staf, 'staff')
            ->get(route('admin.pmb'))
            ->assertOk()
            ->assertSee(route('admin.pindahan.create', ['pendaftar' => $pendaftar->uuid]), false);
    });
});

describe('transkrip asal dan konversi', function () {
    it('mencatat seluruh baris transkrip sebagai usulan transfer dari PT asal', function () {
        $m = pindahanBaru();
        [$a, $b] = [mkPindahan(3), mkPindahan(2)];

        $usulan = $this->pindahan->ajukanTranskrip($m, [
            new BarisTranskripData($a->id, 'Algoritma', 3, 'A'),
            new BarisTranskripData($b->id, 'Struktur Data', 2, 'AB'),
        ]);

        expect($usulan)->toHaveCount(2)
            ->and($usulan->pluck('status')->unique()->all())->toBe([StatusKonversi::Diajukan])
            ->and($usulan->pluck('jenis')->unique()->all())->toBe([JenisKonversi::Transfer])
            ->and($usulan->pluck('asal_institusi')->unique()->all())->toBe(['Universitas Merdeka Nusantara']);
    });

    it('memakai jenis RPL tanpa institusi untuk RPL perolehan', function () {
        $m = pindahanBaru(['jenis_daftar' => JenisDaftar::RplPerolehan->value]);

        $usulan = $this->pindahan->ajukanTranskrip($m, [
            new BarisTranskripData(mkPindahan()->id, 'Lima tahun sebagai administrator jaringan'),
        ])->first();

        expect($usulan->jenis)->toBe(JenisKonversi::Rpl)->and($usulan->asal_institusi)->toBeNull();
    });

    it('menolak seluruh transkrip bila satu barisnya sudah ditempuh di sini', function () {
        $m = pindahanBaru();
        [$a, $b] = [mkPindahan(), mkPindahan()];

        $kelas = KelasKuliah::factory()->create([
            'tahun_akademik_id' => $this->term->id, 'mata_kuliah_id' => $b->id, 'prodi_id' => $this->prodi->id, 'sks' => 3,
        ]);
        $krs = Krs::create(['mahasiswa_id' => $m->id, 'tahun_akademik_id' => $this->term->id, 'semester_ke' => 1, 'batas_sks' => 24, 'status' => 'disetujui']);
        $detail = KrsDetail::create(['krs_id' => $krs->id, 'kelas_kuliah_id' => $kelas->id, 'sks' => 3]);
        Nilai::create([
            'krs_detail_id' => $detail->id, 'kelas_kuliah_id' => $kelas->id, 'mahasiswa_id' => $m->id,
            'nilai_angka' => 80, 'nilai_huruf' => 'A', 'bobot' => 4, 'is_final' => true,
        ]);

        expect(fn () => $this->pindahan->ajukanTranskrip($m, [
            new BarisTranskripData($a->id, 'Algoritma'),
            new BarisTranskripData($b->id, 'Basis Data'),
        ]))->toThrow(AturanAkademikException::class, 'sudah ditempuh');

        // Semua atau tidak sama sekali: baris pertama yang sah pun tidak tersimpan.
        expect(KonversiKredit::where('mahasiswa_id', $m->id)->count())->toBe(0);
    });

    it('menolak transkrip untuk mahasiswa yang bukan pindahan', function () {
        $biasa = Mahasiswa::factory()->create(['prodi_id' => $this->prodi->id]);

        $this->pindahan->ajukanTranskrip($biasa, [new BarisTranskripData(mkPindahan()->id, 'Algoritma')]);
    })->throws(AturanAkademikException::class, 'Tetapkan dulu jenis daftar');

    it('menghitung SKS diakui dari konversi yang disetujui, termasuk saat dicabut', function () {
        $m = pindahanBaru();

        $satu = akuiPindahan($m, mkPindahan(3));
        akuiPindahan($m, mkPindahan(2));

        expect($m->fresh()->sks_diakui)->toBe(5);

        $this->konversi->cabut($satu, $this->staf, 'Transkrip asal tidak terlegalisasi.');
        expect($m->fresh()->sks_diakui)->toBe(2);
    });

    it('tidak menghitung usulan yang belum atau tidak disetujui', function () {
        $m = pindahanBaru();
        $usulan = $this->pindahan->ajukanTranskrip($m, [new BarisTranskripData(mkPindahan()->id, 'Algoritma')])->first();

        $this->konversi->tolak($usulan, $this->staf, 'Capaian pembelajaran tidak setara.');

        expect($m->fresh()->sks_diakui)->toBeNull();
    });

    it('tunduk pada batas persentase yang sama saat persetujuan', function () {
        config(['academic.konversi.maks_persen' => 2]); // 2% x 144 = 2 SKS

        $m = pindahanBaru();
        $usulan = $this->pindahan->ajukanTranskrip($m, [new BarisTranskripData(mkPindahan(3)->id, 'Algoritma')])->first();

        expect($this->pindahan->ringkasan($m)->usulanMelebihiSisa())->toBeTrue();
        expect(fn () => $this->konversi->setujui($usulan, $this->staf))
            ->toThrow(AturanAkademikException::class, 'Melebihi batas pengakuan');

        expect($m->fresh()->sks_diakui)->toBeNull();
    });
});

describe('koreksi asal', function () {
    it('dapat mengubah peserta didik baru menjadi pindahan lalu kembali', function () {
        $m = Mahasiswa::factory()->create(['prodi_id' => $this->prodi->id]);

        $this->pindahan->perbaruiAsal($m, asalPindahan());
        expect($m->fresh()->jenis_daftar)->toBe(JenisDaftar::Pindahan);

        akuiPindahan($m->fresh(), mkPindahan(3));
        expect($m->fresh()->sks_diakui)->toBe(3);

        $this->pindahan->perbaruiAsal($m->fresh(), AsalPendidikanData::fromArray(['jenis_daftar' => JenisDaftar::Baru->value]));
        $m->refresh();

        expect($m->jenis_daftar)->toBe(JenisDaftar::Baru)
            ->and($m->pt_asal)->toBeNull()
            ->and($m->sks_diakui)->toBeNull();
    });

    it('menolak koreksi setelah riwayat pendidikan tercatat di PDDIKTI', function () {
        $m = pindahanBaru();
        $m->forceFill(['feeder_registrasi_id' => 'reg-123'])->saveQuietly();

        $this->pindahan->perbaruiAsal($m, asalPindahan(['pt_asal' => 'Universitas Lain']));
    })->throws(AturanAkademikException::class, 'sudah tercatat di PDDIKTI');
});

describe('semester masuk dan batas SKS semester pertama', function () {
    it('menempatkan pindahan sejauh SKS diakuinya membawa', function () {
        $m = pindahanBaru();
        $hitung = app(BatasSksCalculator::class);

        expect($hitung->semesterKe($m, $this->term))->toBe(1);

        foreach (range(1, 7) as $_) {
            akuiPindahan($m, mkPindahan(6));
        }

        // 42 SKS diakui, 20 per semester: semester 3.
        expect($m->fresh()->sks_diakui)->toBe(42)
            ->and($hitung->semesterKe($m->fresh(), $this->term))->toBe(3);
    });

    it('menaikkan batas SKS KRS pertama dari nilai konversi yang bagus', function () {
        $m = pindahanBaru();
        akuiPindahan($m, mkPindahan(3), 'A');
        akuiPindahan($m, mkPindahan(3), 'B');

        // Rata-rata (4 x 3 + 3 x 3) / 6 = 3,50: baris matriks tertinggi.
        $krs = app(KrsService::class)->bukaAtauAmbil($m->fresh(), $this->term);

        expect($krs->batas_sks)->toBe(24)
            ->and((float) $krs->ips_acuan)->toBe(3.5)
            ->and($krs->semester_ke)->toBe(1);
    });

    it('memakai batas bawaan bila konversinya tanpa nilai', function () {
        $m = pindahanBaru(['jenis_daftar' => JenisDaftar::RplPerolehan->value]);
        akuiPindahan($m, mkPindahan(3), null);

        $acuan = app(BatasSksCalculator::class)->untuk($m->fresh(), $this->term);

        expect($acuan['batas'])->toBe((int) config('academic.krs.default_credits'))
            ->and($acuan['ips'])->toBeNull();
    });

    it('mengabaikan nilai konversi begitu ada semester final di sini', function () {
        $m = pindahanBaru();
        akuiPindahan($m, mkPindahan(3), 'A');

        $lalu = TahunAkademik::factory()->term(2025, SemesterType::Genap)->terkunci()->create();
        StatusMahasiswa::create([
            'mahasiswa_id' => $m->id, 'tahun_akademik_id' => $lalu->id, 'status' => StudentStatus::Aktif,
            'semester_ke' => 1, 'ips' => 2.10, 'ipk' => 2.10, 'is_final' => true, 'finalized_at' => now(),
        ]);

        expect(app(BatasSksCalculator::class)->untuk($m->fresh(), $this->term)['batas'])->toBe(20);
    });
});

describe('masa studi dari semester masuk asal', function () {
    beforeEach(function () {
        config(['academic.evaluasi.masa_studi_maksimum' => 4, 'academic.evaluasi.tahap' => []]);

        $this->mhs = Mahasiswa::factory()->create([
            'prodi_id' => $this->prodi->id,
            'jenis_daftar' => JenisDaftar::Pindahan,
            'pt_asal' => 'Universitas Merdeka Nusantara',
            'prodi_asal' => 'Teknik Informatika',
            'term_masuk' => '20241',
            'term_masuk_asal' => '20231',
        ]);

        foreach ([[2024, SemesterType::Ganjil], [2024, SemesterType::Genap]] as $ke => [$tahun, $jenis]) {
            $term = TahunAkademik::factory()->term($tahun, $jenis)->terkunci()->create();
            StatusMahasiswa::create([
                'mahasiswa_id' => $this->mhs->id, 'tahun_akademik_id' => $term->id, 'status' => StudentStatus::Aktif,
                'semester_ke' => $ke + 1, 'ips' => 3.0, 'ipk' => 3.0, 'is_final' => true, 'finalized_at' => now(),
            ]);
            $this->terakhir = $term;
        }
    });

    it('menghitung dua semester di asal', function () {
        expect($this->mhs->semesterDiAsal())->toBe(2);
    });

    it('tidak menghitung semester di asal secara bawaan', function () {
        app(EvaluasiStudiService::class)->jalankan($this->terakhir);

        expect(EvaluasiStudi::where('mahasiswa_id', $this->mhs->id)->where('tahap', 'Batas Masa Studi')->exists())->toBeFalse();
    });

    it('menghitungnya bila kampus memilih demikian', function () {
        config(['academic.pindahan.masa_studi_dari_asal' => true]);

        app(EvaluasiStudiService::class)->jalankan($this->terakhir);

        // 2 semester di sini + 2 di asal = batas 4: peringatan, belum gagal.
        expect(EvaluasiStudi::where('mahasiswa_id', $this->mhs->id)->where('tahap', 'Batas Masa Studi')->value('temuan')?->value)
            ->toBe('peringatan');
    });
});

describe('formulir intake', function () {
    it('mendaftarkan pindahan lewat layar dan membuka profilnya', function () {
        $respons = $this->actingAs($this->staf, 'staff')->post(route('admin.pindahan.store'), formIntake());

        $m = Mahasiswa::where('email', 'rizky.pindahan@contoh.test')->firstOrFail();

        $respons->assertRedirect(route('admin.pindahan.show', $m))
            ->assertSessionHas('kata_sandi_baru');

        expect($m->jenis_daftar)->toBe(JenisDaftar::Pindahan);

        $this->actingAs($this->staf, 'staff')->get(route('admin.pindahan.show', $m))
            ->assertOk()
            ->assertSee('Universitas Merdeka Nusantara');
    });

    it('menolak pindahan tanpa PT asal saat input, seperti FeederValidator', function () {
        $this->actingAs($this->staf, 'staff')
            ->post(route('admin.pindahan.store'), formIntake(['pt_asal' => '']))
            ->assertSessionHasErrors(['pt_asal' => __('pindahan.validasi.pt_asal')]);

        expect(Mahasiswa::count())->toBe(0);
    });

    it('menolak NIK yang akan ditolak Feeder', function () {
        $this->actingAs($this->staf, 'staff')
            ->post(route('admin.pindahan.store'), formIntake(['nik' => '327301120303']))
            ->assertSessionHasErrors('nik');
    });

    it('menolak jenis daftar di luar pindahan dan RPL', function () {
        $this->actingAs($this->staf, 'staff')
            ->post(route('admin.pindahan.store'), formIntake(['jenis_daftar' => JenisDaftar::Baru->value]))
            ->assertSessionHasErrors('jenis_daftar');
    });

    it('menolak semester masuk asal yang tidak sebelum semester masuk di sini', function () {
        $this->actingAs($this->staf, 'staff')
            ->post(route('admin.pindahan.store'), formIntake(['term_masuk_asal' => $this->term->kode]))
            ->assertSessionHasErrors('term_masuk_asal');

        $this->actingAs($this->staf, 'staff')
            ->post(route('admin.pindahan.store'), formIntake(['term_masuk_asal' => '2024']))
            ->assertSessionHasErrors('term_masuk_asal');
    });

    it('tidak menerima SKS diakui yang diketik', function () {
        $this->actingAs($this->staf, 'staff')
            ->post(route('admin.pindahan.store'), formIntake(['sks_diakui' => 60]))
            ->assertSessionHasNoErrors();

        expect(Mahasiswa::where('email', 'rizky.pindahan@contoh.test')->value('sks_diakui'))->toBeNull();
    });

    it('meminta izin PMB untuk pintu pendaftar', function () {
        $peran = Role::create(['name' => 'registrar-saja', 'guard_name' => 'staff']);
        $peran->givePermissionTo(['mahasiswa.view', 'mahasiswa.manage']);
        $registrar = Staff::factory()->create();
        $registrar->assignRole($peran);

        $pendaftar = pendaftarTransfer();

        $this->actingAs($registrar, 'staff')
            ->post(route('admin.pindahan.store'), [
                'pmb_pendaftar' => $pendaftar->uuid,
                'tahun_akademik_id' => $this->term->id,
                'jenis_daftar' => JenisDaftar::Pindahan->value,
                'pt_asal' => 'Universitas Merdeka Nusantara',
                'prodi_asal' => 'Teknik Informatika',
            ])
            ->assertForbidden();

        // Pintu langsung tetap terbuka untuknya.
        $this->actingAs($registrar, 'staff')->get(route('admin.pindahan.create'))->assertOk();
    });

    it('meregistrasi pendaftar PMB lewat layar', function () {
        $pendaftar = pendaftarTransfer();

        $this->actingAs($this->staf, 'staff')
            ->get(route('admin.pindahan.create', ['pendaftar' => $pendaftar->uuid]))
            ->assertOk()
            ->assertSee($pendaftar->nomor_pendaftaran);

        $this->actingAs($this->staf, 'staff')
            ->post(route('admin.pindahan.store'), [
                'pmb_pendaftar' => $pendaftar->uuid,
                'tahun_akademik_id' => $this->term->id,
                'jenis_daftar' => JenisDaftar::RplTransfer->value,
                'pt_asal' => 'Politeknik Negeri Contoh',
                'prodi_asal' => 'Teknik Komputer',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        expect($pendaftar->fresh()->mahasiswa->jenis_daftar)->toBe(JenisDaftar::RplTransfer);
    });

    it('menolak staf tanpa izin mengelola mahasiswa', function () {
        $keuangan = Staff::factory()->create();
        $keuangan->assignRole('keuangan');

        $this->actingAs($keuangan, 'staff')->get(route('admin.pindahan'))->assertOk();
        $this->actingAs($keuangan, 'staff')->get(route('admin.pindahan.create'))->assertForbidden();
        $this->actingAs($keuangan, 'staff')->post(route('admin.pindahan.store'), formIntake())->assertForbidden();
    });

    it('memeriksa baris transkrip sebelum service menyentuhnya', function () {
        $m = pindahanBaru();
        $mk = mkPindahan();
        $lain = MataKuliah::factory()->create(['prodi_id' => Prodi::factory()->create()->id]);

        $this->actingAs($this->staf, 'staff')
            ->post(route('admin.pindahan.transkrip', $m), ['baris' => [
                ['mata_kuliah_id' => $mk->id, 'asal_nama' => 'Algoritma'],
                ['mata_kuliah_id' => $mk->id, 'asal_nama' => 'Algoritma II'],
            ]])
            ->assertSessionHasErrors('baris.1.mata_kuliah_id');

        $this->actingAs($this->staf, 'staff')
            ->post(route('admin.pindahan.transkrip', $m), ['baris' => [
                ['mata_kuliah_id' => $lain->id, 'asal_nama' => 'Algoritma'],
            ]])
            ->assertSessionHasErrors('baris.0.mata_kuliah_id');

        // Baris cadangan yang kosong diabaikan, bukan dianggap salah.
        $this->actingAs($this->staf, 'staff')
            ->post(route('admin.pindahan.transkrip', $m), ['baris' => [
                ['mata_kuliah_id' => $mk->id, 'asal_nama' => 'Algoritma', 'asal_sks' => 3, 'asal_nilai' => 'A'],
                ['mata_kuliah_id' => '', 'asal_nama' => '', 'asal_sks' => '', 'asal_nilai' => ''],
            ]])
            ->assertSessionHasNoErrors();

        expect(KonversiKredit::where('mahasiswa_id', $m->id)->count())->toBe(1);
    });

    it('menyarankan nama PT dari referensi PDDIKTI dan intake sebelumnya', function () {
        FeederRef::create(['ref_type' => 'perguruan_tinggi', 'code' => 'pt-1', 'name' => 'Universitas Negeri Contoh', 'synced_at' => now()]);
        pindahanBaru(['pt_asal' => 'Universitas Contoh Swasta']);

        $this->actingAs($this->staf, 'staff')
            ->getJson(route('admin.pindahan.referensi', ['tipe' => 'pt', 'q' => 'contoh']))
            ->assertOk()
            ->assertExactJson(['Universitas Negeri Contoh', 'Universitas Contoh Swasta']);
    });
});

describe('kesiapan Feeder', function () {
    beforeEach(function () {
        FeederMapping::create([
            'group' => 'jenis_daftar',
            'local_value' => JenisDaftar::Pindahan->value,
            'feeder_code' => '2',
            'feeder_label' => 'Pindahan',
        ]);
    });

    it('meloloskan pindahan yang diinput lewat layar ke validator riwayat pendidikan', function () {
        $this->actingAs($this->staf, 'staff')->post(route('admin.pindahan.store'), formIntake());
        $m = Mahasiswa::where('email', 'rizky.pindahan@contoh.test')->firstOrFail();
        $m->forceFill(['feeder_id' => 'biodata-1'])->saveQuietly();

        $validator = app(FeederValidator::class);
        $galat = fn (): array => $validator->isuUntuk('riwayat_pendidikan', $m->fresh()->load('prodi'))
            ->where('severity', 'error')->pluck('rule')->all();

        // Sebelum ada konversi yang disetujui, validator masih menahannya.
        expect($galat())->toBe(['sks_diakui_kosong']);

        $mk = mkPindahan(3);
        $this->actingAs($this->staf, 'staff')->post(route('admin.pindahan.transkrip', $m), ['baris' => [
            ['mata_kuliah_id' => $mk->id, 'asal_nama' => 'Algoritma', 'asal_sks' => 3, 'asal_nilai' => 'A'],
        ]])->assertSessionHasNoErrors();

        $this->konversi->setujui(KonversiKredit::where('mahasiswa_id', $m->id)->firstOrFail(), $this->staf, null, 'A');

        expect($galat())->toBe([])
            ->and($validator->isuUntuk('mahasiswa', $m->fresh())->where('severity', 'error')->all())->toBe([]);
    });

    it('menunjukkan isu Feeder di profil pindahan', function () {
        $m = pindahanBaru();

        $this->actingAs($this->staf, 'staff')->get(route('admin.pindahan.show', $m))
            ->assertOk()
            ->assertSee('wajib membawa jumlah SKS yang diakui');
    });
});

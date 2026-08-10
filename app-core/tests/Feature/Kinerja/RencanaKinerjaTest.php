<?php

declare(strict_types=1);

use App\Enums\JenisUnitKerja;
use App\Enums\SemesterType;
use App\Enums\StudentStatus;
use App\Enums\SumberRealisasi;
use App\Exceptions\AturanAkademikException;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Kinerja\CapaianKinerja;
use App\Models\Kinerja\PeriodeKinerja;
use App\Models\Kinerja\SasaranKinerja;
use App\Models\Kinerja\UkuranKinerja;
use App\Models\Sdm\Staff;
use App\Models\Sdm\UnitKerja;
use App\Services\Kinerja\KinerjaService;
use App\Services\Kinerja\PengukurKinerja;
use Database\Seeders\RolePermissionSeeder;

/**
 * Rencana kinerja.
 *
 * Four properties carry the module, and every one of them is a refusal: a
 * computed measure will not take a typed number, the cascade will not accept a
 * cycle, a locked period will not move, and nothing here scores a person.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->term = TahunAkademik::factory()->term(2026, SemesterType::Ganjil)->berjalan()->aktif()->create();
    $this->prodi = Prodi::factory()->create();

    $this->kinerja = app(KinerjaService::class);
    $this->pengukur = app(PengukurKinerja::class);

    $this->staf = Staff::factory()->create();
    $this->staf->assignRole('super-admin');

    $this->periode = PeriodeKinerja::create([
        'nama' => 'Rencana Kinerja 2026',
        'tahun' => 2026,
        'tahun_akademik_id' => $this->term->id,
        'mulai' => '2026-01-01',
        'selesai' => '2026-12-31',
    ]);
});

function unitKinerja(string $kode, ?Prodi $prodi = null, ?UnitKerja $induk = null): UnitKerja
{
    return UnitKerja::create([
        'kode' => $kode,
        'nama' => 'Unit '.$kode,
        'jenis' => $prodi ? JenisUnitKerja::Akademik : JenisUnitKerja::Struktural,
        'prodi_id' => $prodi?->id,
        'parent_id' => $induk?->id,
    ]);
}

function sasaranKinerja(UnitKerja $unit, string $judul = 'Sasaran uji', ?SasaranKinerja $induk = null): SasaranKinerja
{
    return test()->kinerja->buatSasaran(test()->periode, $unit, [
        'judul' => $judul,
        'parent_id' => $induk?->id,
    ]);
}

describe('ukuran yang dihitung', function () {
    it('menolak ukuran dihitung tanpa indikator terdaftar', function () {
        /*
         * Tanpa pemeriksaan ini, barisnya adalah target yang tidak pernah dapat
         * terealisasi — dan tidak ada yang tahu sampai tinjauan yang justru
         * menjadi alasan ia dibuat.
         */
        $sasaran = sasaranKinerja(unitKinerja('BIRO'));

        expect(fn () => $this->kinerja->tambahUkuran($sasaran, [
            'nama' => 'Sesuatu',
            'sumber_realisasi' => SumberRealisasi::Dihitung,
            'indikator_kunci' => 'indikator_karangan',
            'target' => 100,
        ]))->toThrow(AturanAkademikException::class, 'indikator terdaftar');
    });

    it('menolak realisasi diketik untuk ukuran yang dihitung', function () {
        // Bukan dilarang izin — memang tidak ada jalur sahnya. Angka yang
        // ditimpa manual tidak dapat dibedakan lagi dari yang asli.
        $sasaran = sasaranKinerja(unitKinerja('BIRO'));

        $ukuran = $this->kinerja->tambahUkuran($sasaran, [
            'nama' => 'Mahasiswa aktif',
            'sumber_realisasi' => SumberRealisasi::Dihitung,
            'indikator_kunci' => 'mahasiswa_aktif',
            'target' => 100,
        ]);

        $this->kinerja->jalankan($this->periode);

        expect(fn () => $this->kinerja->catatCapaian($ukuran, 999, now()->toDateString(), $this->staf))
            ->toThrow(AturanAkademikException::class, 'tidak dapat diketik');
    });

    it('membuang kunci indikator pada ukuran yang dilaporkan', function () {
        // Kunci pada ukuran non-hitung menyiratkan tautan yang tidak pernah
        // ditelusuri, dan cepat atau lambat ada yang memercayainya.
        $sasaran = sasaranKinerja(unitKinerja('BIRO'));

        $ukuran = $this->kinerja->tambahUkuran($sasaran, [
            'nama' => 'Kepuasan layanan',
            'sumber_realisasi' => SumberRealisasi::Dilaporkan,
            'indikator_kunci' => 'mahasiswa_aktif',
            'target' => 90,
        ]);

        expect($ukuran->indikator_kunci)->toBeNull();
    });

    it('mengukur dari data sungguhan dan mempersempit ke prodi unitnya', function () {
        $prodiLain = Prodi::factory()->create();

        Mahasiswa::factory()->count(3)->create([
            'prodi_id' => $this->prodi->id,
            'status' => StudentStatus::Aktif,
        ]);
        Mahasiswa::factory()->count(5)->create([
            'prodi_id' => $prodiLain->id,
            'status' => StudentStatus::Aktif,
        ]);

        $unit = unitKinerja('PRODI-A', $this->prodi);

        expect($this->pengukur->ukur('mahasiswa_aktif', $unit, $this->term))->toBe(3.0);
    });

    it('menjumlahkan prodi di bawah unit induk', function () {
        // Angka yang sebenarnya dimaksud seorang dekan dengan "fakultas saya".
        $prodiB = Prodi::factory()->create();

        Mahasiswa::factory()->count(3)->create(['prodi_id' => $this->prodi->id, 'status' => StudentStatus::Aktif]);
        Mahasiswa::factory()->count(4)->create(['prodi_id' => $prodiB->id, 'status' => StudentStatus::Aktif]);

        $fakultas = unitKinerja('FT');
        unitKinerja('PRODI-A', $this->prodi, $fakultas);
        unitKinerja('PRODI-B', $prodiB, $fakultas);

        expect($this->pengukur->ukur('mahasiswa_aktif', $fakultas->refresh(), $this->term))->toBe(7.0);
    });

    it('mencatat capaian otomatis untuk seluruh ukuran yang dihitung', function () {
        Mahasiswa::factory()->count(2)->create(['prodi_id' => $this->prodi->id, 'status' => StudentStatus::Aktif]);

        $sasaran = sasaranKinerja(unitKinerja('PRODI-A', $this->prodi));

        $ukuran = $this->kinerja->tambahUkuran($sasaran, [
            'nama' => 'Mahasiswa aktif',
            'sumber_realisasi' => SumberRealisasi::Dihitung,
            'indikator_kunci' => 'mahasiswa_aktif',
            'target' => 10,
        ]);

        $this->kinerja->jalankan($this->periode);

        expect($this->kinerja->ukurOtomatis($this->periode->refresh()))->toBe(1)
            ->and($ukuran->refresh()->realisasi())->toBe(2.0)
            ->and($ukuran->persenCapaian())->toBe(20.0);
    });
});

describe('cascade', function () {
    it('menolak sasaran menjadi induk dirinya sendiri', function () {
        $sasaran = sasaranKinerja(unitKinerja('BIRO'));

        expect(fn () => $this->kinerja->perbaruiSasaran($sasaran, ['parent_id' => $sasaran->id]))
            ->toThrow(AturanAkademikException::class, 'induk dirinya sendiri');
    });

    it('menolak induk yang merupakan turunannya sendiri', function () {
        $a = sasaranKinerja(unitKinerja('A'), 'A');
        $b = sasaranKinerja(unitKinerja('B'), 'B', $a);
        $c = sasaranKinerja(unitKinerja('C'), 'C', $b);

        expect(fn () => $this->kinerja->perbaruiSasaran($a, ['parent_id' => $c->id]))
            ->toThrow(AturanAkademikException::class, 'lingkaran');
    });

    it('menolak induk dari periode lain', function () {
        // Cascade yang menyeberang periode membuat rencana tahun ini
        // menggantung pada sasaran yang sudah dikunci tahun lalu.
        $lain = PeriodeKinerja::create([
            'nama' => 'Rencana 2025',
            'tahun' => 2025,
            'mulai' => '2025-01-01',
            'selesai' => '2025-12-31',
        ]);

        $asing = $this->kinerja->buatSasaran($lain, unitKinerja('LAIN'), ['judul' => 'Sasaran 2025']);
        $sasaran = sasaranKinerja(unitKinerja('BIRO'));

        expect(fn () => $this->kinerja->perbaruiSasaran($sasaran, ['parent_id' => $asing->id]))
            ->toThrow(AturanAkademikException::class, 'periode yang sama');
    });
});

describe('penguncian', function () {
    it('membekukan target dan realisasi terhadap koreksi sesudahnya', function () {
        /*
         * Versi pertama tes ini menambah mahasiswa sesudah penguncian dan
         * memastikan angkanya tidak berubah — yang lolos juga tanpa pembekuan,
         * karena baris capaian memang sudah berupa snapshot. Tes itu tidak
         * memaku apa pun.
         *
         * Yang sebenarnya dilindungi pembekuan adalah **koreksi pada capaian
         * dan targetnya sendiri**: baris check-in yang disunting, atau target
         * yang diubah lewat jalur lain. Laporan yang sudah dikunci harus
         * terbaca sama tahun depan seperti pada hari ia dilaporkan.
         */
        Mahasiswa::factory()->count(2)->create(['prodi_id' => $this->prodi->id, 'status' => StudentStatus::Aktif]);

        $sasaran = sasaranKinerja(unitKinerja('PRODI-A', $this->prodi));
        $ukuran = $this->kinerja->tambahUkuran($sasaran, [
            'nama' => 'Mahasiswa aktif',
            'sumber_realisasi' => SumberRealisasi::Dihitung,
            'indikator_kunci' => 'mahasiswa_aktif',
            'target' => 10,
        ]);

        $this->kinerja->jalankan($this->periode);
        $this->kinerja->ukurOtomatis($this->periode->refresh());
        $this->kinerja->kunci($this->periode->refresh(), $this->staf);

        // Baris capaian dan targetnya disunting langsung sesudah penguncian.
        CapaianKinerja::where('ukuran_kinerja_id', $ukuran->id)->update(['nilai' => 99]);
        UkuranKinerja::whereKey($ukuran->id)->update(['target' => 1]);

        expect($ukuran->refresh()->realisasi())->toBe(2.0)
            ->and($ukuran->targetBerlaku())->toBe(10.0)
            ->and($ukuran->beku())->toBeTrue();
    });

    it('menolak perubahan sasaran setelah dikunci', function () {
        $sasaran = sasaranKinerja(unitKinerja('BIRO'));

        $this->kinerja->jalankan($this->periode);
        $this->kinerja->kunci($this->periode->refresh(), $this->staf);

        expect(fn () => $this->kinerja->perbaruiSasaran($sasaran->refresh(), ['judul' => 'Diubah']))
            ->toThrow(AturanAkademikException::class, 'sudah dikunci');
    });

    it('menolak dikunci dua kali', function () {
        $this->kinerja->jalankan($this->periode);
        $this->kinerja->kunci($this->periode->refresh(), $this->staf);

        expect(fn () => $this->kinerja->kunci($this->periode->refresh(), $this->staf))
            ->toThrow(AturanAkademikException::class, 'sudah dikunci');
    });

    it('menolak capaian pada periode yang masih draf', function () {
        $sasaran = sasaranKinerja(unitKinerja('BIRO'));
        $ukuran = $this->kinerja->tambahUkuran($sasaran, [
            'nama' => 'Kepuasan',
            'sumber_realisasi' => SumberRealisasi::Dilaporkan,
            'target' => 90,
        ]);

        expect(fn () => $this->kinerja->catatCapaian($ukuran, 80, now()->toDateString(), $this->staf))
            ->toThrow(AturanAkademikException::class, 'tidak menerima capaian');
    });
});

describe('capaian bukan penilaian orang', function () {
    it('membalik persentase untuk ukuran yang makin kecil makin baik', function () {
        // Angka putus studi separuh dari batasnya adalah 200% dari sasaran,
        // bukan 50%.
        $sasaran = sasaranKinerja(unitKinerja('BIRO'));

        $ukuran = $this->kinerja->tambahUkuran($sasaran, [
            'nama' => 'Angka putus studi',
            'sumber_realisasi' => SumberRealisasi::Dilaporkan,
            'target' => 4,
            'semakin_besar_semakin_baik' => false,
        ]);

        $this->kinerja->jalankan($this->periode);
        $this->kinerja->catatCapaian($ukuran, 2, now()->toDateString(), $this->staf);

        expect($ukuran->refresh()->persenCapaian())->toBe(200.0);
    });

    it('membedakan belum terukur dari nol', function () {
        /*
         * Nol dan "belum diketahui" adalah dua keadaan berbeda, dan layar yang
         * menampilkannya serupa mengundang percakapan yang salah.
         */
        $sasaran = sasaranKinerja(unitKinerja('BIRO'));

        $ukuran = $this->kinerja->tambahUkuran($sasaran, [
            'nama' => 'Kepuasan',
            'sumber_realisasi' => SumberRealisasi::Dilaporkan,
            'target' => 90,
        ]);

        expect($ukuran->realisasi())->toBeNull()
            ->and($ukuran->persenCapaian())->toBeNull()
            ->and($ukuran->statusCapaian())->toBeNull();
    });

    it('menurunkan penanggung jawab dari kepala unit, bukan menyimpannya', function () {
        /*
         * Dekan berganti tiap empat tahun. Sasaran tidak ikut pindah ke mantan
         * dekan — ia tetap milik unitnya.
         */
        $unit = unitKinerja('FT');
        $sasaran = sasaranKinerja($unit);

        expect($sasaran->penanggungJawab())->toBeNull();

        $unit->update(['kepala_staff_id' => $this->staf->id]);

        expect($sasaran->refresh()->penanggungJawab()?->id)->toBe($this->staf->id);
    });
});

describe('layar', function () {
    it('menampilkan sasaran, ukuran, dan batas modulnya', function () {
        Mahasiswa::factory()->count(2)->create(['prodi_id' => $this->prodi->id, 'status' => StudentStatus::Aktif]);

        $sasaran = sasaranKinerja(unitKinerja('PRODI-A', $this->prodi), 'Meningkatkan mutu lulusan');
        $this->kinerja->tambahUkuran($sasaran, [
            'nama' => 'Mahasiswa aktif',
            'sumber_realisasi' => SumberRealisasi::Dihitung,
            'indikator_kunci' => 'mahasiswa_aktif',
            'target' => 10,
        ]);

        $this->actingAs($this->staf, 'staff')
            ->get('/admin/kinerja')
            ->assertOk()
            ->assertSee('Meningkatkan mutu lulusan')
            ->assertSee('Dihitung dari data')
            // Batasnya dinyatakan di layar, bukan hanya di dokumen.
            ->assertSee('bukan', false)
            ->assertSee('dasbor IKU', false);
    });

    it('menolak staf tanpa izin mengunci periode', function () {
        $keuangan = Staff::factory()->create();
        $keuangan->assignRole('keuangan');

        $this->actingAs($keuangan, 'staff')
            ->post('/admin/kinerja/periode/'.$this->periode->uuid.'/kunci')
            ->assertForbidden();
    });
});

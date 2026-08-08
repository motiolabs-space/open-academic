<?php

declare(strict_types=1);

use App\Exceptions\AturanAkademikException;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\CutiMahasiswa;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Pmb\PmbBerkas;
use App\Models\Pmb\PmbGelombang;
use App\Models\Pmb\PmbPendaftar;
use App\Models\Sdm\Staff;
use App\Services\Berkas\BerkasService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    Storage::fake('local');

    $this->term = TahunAkademik::factory()->berjalan()->create(['is_active' => true]);
    $this->prodi = Prodi::factory()->create();

    $this->baak = Staff::factory()->create();
    $this->baak->assignRole('baak');

    $this->berkas = app(BerkasService::class);

    $this->gelombang = PmbGelombang::create([
        'tahun_akademik_id' => $this->term->id,
        'kode' => 'PMB-1', 'nama' => 'Gelombang I', 'jalur' => 'reguler',
        'tanggal_mulai' => now()->subMonth(), 'tanggal_selesai' => now()->addMonth(),
        'biaya_pendaftaran' => 0, 'is_active' => true,
    ]);

    $this->pendaftar = PmbPendaftar::create([
        'pmb_gelombang_id' => $this->gelombang->id,
        'nomor_pendaftaran' => 'REG-000001',
        'nama' => 'Calon Mahasiswa',
        'email' => 'calon@demo.test',
        'prodi_pilihan_1_id' => $this->prodi->id,
        'status' => 'seleksi',
    ]);
});

describe('penyimpanan', function () {
    it('tidak pernah memakai nama berkas dari pengunggah', function () {
        // Nama berkas datang dari klien, jadi bisa berisi path traversal,
        // null byte, atau ekstensi kedua seperti "ktp.pdf.php".
        $path = $this->berkas->simpan(
            UploadedFile::fake()->create('../../.env.pdf', 10, 'application/pdf'),
            'pmb/uji',
        );

        expect($path)->not->toContain('..')
            ->and($path)->not->toContain('.env')
            ->and($path)->toStartWith('pmb/uji/')
            ->and($path)->toEndWith('.pdf');
    });

    it('memberi nama yang tidak dapat ditebak', function () {
        $a = $this->berkas->simpan(UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'), 'pmb/uji');
        $b = $this->berkas->simpan(UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'), 'pmb/uji');

        // Path berurutan adalah undangan terbuka untuk mencoba nomor berikutnya,
        // sekalipun sudah ada pemeriksaan izin di depannya.
        expect($a)->not->toBe($b);
    });

    it('menolak berjalan di atas disk publik', function () {
        // Salah konfigurasi ini menaruh KTP setiap mahasiswa di bawah document
        // root, dan tak ada bagian lain yang akan menyadarinya.
        config(['berkas.disk' => 'public']);

        expect(fn () => $this->berkas->simpan(
            UploadedFile::fake()->create('ktp.pdf', 10, 'application/pdf'),
            'pmb/uji',
        ))->toThrow(AturanAkademikException::class, 'document root');
    });

    it('menghapus berkas dari penyimpanan', function () {
        $path = $this->berkas->simpan(UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'), 'pmb/uji');

        expect($this->berkas->ada($path))->toBeTrue();

        $this->berkas->hapus($path);

        expect($this->berkas->ada($path))->toBeFalse();
    });
});

describe('unggah lewat layar PMB', function () {
    it('menerima PDF dan mencatat nama aslinya sebagai label', function () {
        $this->actingAs($this->baak, 'staff')
            ->post("/admin/pmb/{$this->pendaftar->uuid}/berkas", [
                'jenis' => 'ktp',
                'berkas' => UploadedFile::fake()->create('KTP Budi.pdf', 100, 'application/pdf'),
            ])->assertRedirect()->assertSessionHasNoErrors();

        $berkas = PmbBerkas::firstOrFail();

        expect($berkas->nama_file)->toBe('KTP Budi.pdf')
            ->and($berkas->file_path)->not->toContain('KTP Budi')
            ->and(Storage::disk('local')->exists($berkas->file_path))->toBeTrue();
    });

    it('menolak berkas yang jenisnya tidak diizinkan', function () {
        // Ekstensi hanya bagian dari nama; aturan mimes memeriksa isinya.
        $this->actingAs($this->baak, 'staff')
            ->post("/admin/pmb/{$this->pendaftar->uuid}/berkas", [
                'jenis' => 'ktp',
                'berkas' => UploadedFile::fake()->create('skrip.php', 10, 'application/x-php'),
            ])->assertSessionHasErrors('berkas');

        expect(PmbBerkas::count())->toBe(0);
    });

    it('menolak berkas melebihi batas ukuran', function () {
        config(['berkas.maks_kb' => 100]);

        $this->actingAs($this->baak, 'staff')
            ->post("/admin/pmb/{$this->pendaftar->uuid}/berkas", [
                'jenis' => 'ktp',
                'berkas' => UploadedFile::fake()->create('besar.pdf', 500, 'application/pdf'),
            ])->assertSessionHasErrors('berkas');
    });

    it('menghapus berkas fisiknya saat baris dihapus', function () {
        $this->actingAs($this->baak, 'staff')->post("/admin/pmb/{$this->pendaftar->uuid}/berkas", [
            'jenis' => 'ktp',
            'berkas' => UploadedFile::fake()->create('ktp.pdf', 10, 'application/pdf'),
        ]);

        $berkas = PmbBerkas::firstOrFail();
        $path = $berkas->file_path;

        $this->actingAs($this->baak, 'staff')->delete("/admin/pmb/berkas/{$berkas->id}");

        expect(Storage::disk('local')->exists($path))->toBeFalse()
            ->and(PmbBerkas::count())->toBe(0);
    });
});

describe('otorisasi unduhan', function () {
    beforeEach(function () {
        $this->berkasPmb = PmbBerkas::create([
            'pmb_pendaftar_id' => $this->pendaftar->id,
            'jenis' => 'kk',
            'nama_file' => 'kartu-keluarga.pdf',
            'file_path' => $this->berkas->simpan(
                UploadedFile::fake()->create('kk.pdf', 10, 'application/pdf'),
                'pmb/'.$this->pendaftar->uuid,
            ),
            'is_verified' => false,
        ]);
    });

    it('mengizinkan staf PMB mengunduh berkas pendaftar', function () {
        $this->actingAs($this->baak, 'staff')
            ->get("/berkas/pmb/{$this->berkasPmb->id}")
            ->assertOk();
    });

    it('menolak staf tanpa izin PMB', function () {
        // "Staf mana pun yang sudah masuk" akan membiarkan bagian keuangan
        // membaca kartu keluarga para pendaftar.
        $keuangan = Staff::factory()->create();
        $keuangan->assignRole('keuangan');

        $this->actingAs($keuangan, 'staff')
            ->get("/berkas/pmb/{$this->berkasPmb->id}")
            ->assertForbidden();
    });

    it('menolak tamu yang belum masuk', function () {
        $this->get("/berkas/pmb/{$this->berkasPmb->id}")->assertRedirect('/masuk');
    });
});

describe('otorisasi dokumen cuti', function () {
    beforeEach(function () {
        $this->mahasiswa = Mahasiswa::factory()->create(['prodi_id' => $this->prodi->id]);
        $this->mahasiswa->assignRole('mahasiswa');

        $this->cuti = CutiMahasiswa::create([
            'mahasiswa_id' => $this->mahasiswa->id,
            'tahun_akademik_id' => $this->term->id,
            'jenis' => 'sakit',
            'alasan' => 'Rawat inap sesuai surat keterangan dokter.',
            'status' => 'diajukan',
            'diajukan_at' => now(),
            'dokumen_path' => $this->berkas->simpan(
                UploadedFile::fake()->create('surat-sakit.pdf', 10, 'application/pdf'),
                'cuti/uji',
            ),
        ]);
    });

    it('mengizinkan mahasiswa membuka dokumennya sendiri', function () {
        $this->actingAs($this->mahasiswa, 'mahasiswa')
            ->get("/berkas/cuti/{$this->cuti->uuid}")
            ->assertOk();
    });

    it('menolak mahasiswa lain membuka surat sakit temannya', function () {
        // Inilah kegagalan yang dicegah pemeriksaan ini: rekam medis orang lain
        // hanya berjarak satu tebakan URL.
        $orangLain = Mahasiswa::factory()->create(['prodi_id' => $this->prodi->id]);
        $orangLain->assignRole('mahasiswa');

        $this->actingAs($orangLain, 'mahasiswa')
            ->get("/berkas/cuti/{$this->cuti->uuid}")
            ->assertForbidden();
    });

    it('mengizinkan BAAK yang memutuskan cutinya', function () {
        $this->actingAs($this->baak, 'staff')
            ->get("/berkas/cuti/{$this->cuti->uuid}")
            ->assertOk();
    });

    it('menjawab 404 bila berkasnya hilang dari penyimpanan', function () {
        // Terjadi setelah pemulihan cadangan yang melewatkan direktori storage.
        // Operator perlu tahu dokumen mana yang hilang, bukan jejak galat.
        Storage::disk('local')->delete($this->cuti->dokumen_path);

        $this->actingAs($this->baak, 'staff')
            ->get("/berkas/cuti/{$this->cuti->uuid}")
            ->assertNotFound();
    });
});

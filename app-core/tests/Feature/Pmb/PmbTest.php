<?php

declare(strict_types=1);

use App\Enums\ApplicantStatus;
use App\Enums\StudentStatus;
use App\Exceptions\AturanAkademikException;
use App\Models\Akademik\Kurikulum;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Keuangan\Tarif;
use App\Models\Pmb\PmbGelombang;
use App\Models\Pmb\PmbPendaftar;
use App\Models\Sdm\Staff;
use App\Services\Pmb\NimGenerator;
use App\Services\Pmb\PmbService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->term = TahunAkademik::factory()->berjalan()->create([
        'is_active' => true,
        'tahun_mulai' => 2026,
    ]);

    $this->prodi = Prodi::factory()->create(['kode' => 'IF', 'kode_pddikti' => '55201']);

    $this->gelombang = PmbGelombang::create([
        'tahun_akademik_id' => $this->term->id,
        'kode' => 'PMB-1',
        'nama' => 'Gelombang I',
        'jalur' => 'reguler',
        'tanggal_mulai' => now()->subMonth(),
        'tanggal_selesai' => now()->addMonth(),
        'biaya_pendaftaran' => 0,
        'is_active' => true,
    ]);

    $this->pmb = app(PmbService::class);
});

function pendaftarBaru(array $atribut = []): PmbPendaftar
{
    $test = test();

    return PmbPendaftar::create([
        'pmb_gelombang_id' => $test->gelombang->id,
        'nomor_pendaftaran' => 'REG-'.fake()->unique()->numerify('######'),
        'nama' => 'Calon Mahasiswa',
        'email' => fake()->unique()->safeEmail(),
        'nik' => '3201234567890001',
        'tempat_lahir' => 'Bandung',
        'tanggal_lahir' => '2008-05-14',
        'jenis_kelamin' => 'L',
        'prodi_pilihan_1_id' => $test->prodi->id,
        'status' => ApplicantStatus::Seleksi,
        ...$atribut,
    ]);
}

describe('generator NIM', function () {
    it('mengikuti pola yang dikonfigurasi kampus', function () {
        config(['academic.nim.pattern' => '{yy}{prodi}{seq}', 'academic.nim.sequence_length' => 4]);

        $nim = app(NimGenerator::class)->untuk($this->prodi, 2026);

        // 26 (dua digit tahun) + 55201 (kode PDDIKTI) + 0001
        expect($nim)->toBe('26552010001');
    });

    it('melanjutkan urutan, tidak mengulang dari satu', function () {
        Mahasiswa::factory()->create(['nim' => '26552010007', 'prodi_id' => $this->prodi->id]);

        expect(app(NimGenerator::class)->untuk($this->prodi, 2026))->toBe('26552010008');
    });

    it('tidak pernah memakai ulang NIM mahasiswa yang dihapus', function () {
        // NIM yang sudah dihapus lunak tetap tercetak pada transkrip yang
        // terlanjur terbit. Memberikannya kepada orang lain membuat dua orang
        // berbagi satu identitas.
        $lama = Mahasiswa::factory()->create(['nim' => '26552010001', 'prodi_id' => $this->prodi->id]);
        $lama->delete();

        expect(app(NimGenerator::class)->untuk($this->prodi, 2026))->not->toBe('26552010001');
    });

    it('memakai kode PDDIKTI bila ada, bukan kode internal', function () {
        $tanpaPddikti = Prodi::factory()->create(['kode' => 'SI', 'kode_pddikti' => null]);

        expect(app(NimGenerator::class)->untuk($tanpaPddikti, 2026))->toStartWith('26SI');
    });
});

describe('daftar ulang', function () {
    it('mengubah pendaftar menjadi mahasiswa berakun', function () {
        Kurikulum::factory()->create(['prodi_id' => $this->prodi->id, 'is_active' => true]);

        $pendaftar = pendaftarBaru();
        $this->pmb->luluskan($pendaftar, $this->prodi);

        $hasil = $this->pmb->daftarUlang($pendaftar->fresh(), $this->term);
        $mahasiswa = $hasil['mahasiswa'];

        expect($mahasiswa->nim)->toBe('26552010001')
            ->and($mahasiswa->status)->toBe(StudentStatus::Aktif)
            ->and($mahasiswa->hasRole('mahasiswa'))->toBeTrue()
            ->and($mahasiswa->angkatan)->toBe(2026)
            ->and($mahasiswa->kurikulum_id)->not->toBeNull()
            ->and(Hash::check($hasil['kata_sandi'], $mahasiswa->password))->toBeTrue();

        // Data yang sudah dikumpulkan saat mendaftar ikut terbawa, supaya
        // dorongan biodata ke Feeder tidak tersandung NIK yang hilang.
        expect($mahasiswa->nik)->toBe('3201234567890001')
            ->and($pendaftar->fresh()->status)->toBe(ApplicantStatus::Mahasiswa)
            ->and($pendaftar->fresh()->mahasiswa_id)->toBe($mahasiswa->id);
    });

    it('menerbitkan tagihan awal dari tarif yang berlaku', function () {
        Tarif::create([
            'prodi_id' => $this->prodi->id,
            'komponen' => 'ukt',
            'nama' => 'UKT Semester 1',
            'nominal' => 5_000_000,
            'is_active' => true,
        ]);

        $pendaftar = pendaftarBaru();
        $this->pmb->luluskan($pendaftar, $this->prodi);

        $hasil = $this->pmb->daftarUlang($pendaftar->fresh(), $this->term);

        expect($hasil['tagihan'])->not->toBeNull()
            ->and((int) $hasil['tagihan']->total)->toBe(5_000_000)
            ->and($hasil['tagihan']->item()->count())->toBe(1);
    });

    it('tidak menerbitkan tagihan kosong bila belum ada tarif', function () {
        // Tagihan nol rupiah terbaca sebagai lunas di setiap layar yang
        // membacanya; ketiadaan yang jelas lebih jujur.
        $pendaftar = pendaftarBaru();
        $this->pmb->luluskan($pendaftar, $this->prodi);

        expect($this->pmb->daftarUlang($pendaftar->fresh(), $this->term)['tagihan'])->toBeNull();
    });

    it('menolak mendaftarkan ulang dua kali', function () {
        $pendaftar = pendaftarBaru();
        $this->pmb->luluskan($pendaftar, $this->prodi);
        $this->pmb->daftarUlang($pendaftar->fresh(), $this->term);

        expect(fn () => $this->pmb->daftarUlang($pendaftar->fresh(), $this->term))
            ->toThrow(AturanAkademikException::class, 'sudah terdaftar');

        expect(Mahasiswa::count())->toBe(1);
    });

    it('menolak mendaftarkan ulang pendaftar yang belum lulus seleksi', function () {
        $pendaftar = pendaftarBaru(['status' => ApplicantStatus::Seleksi]);

        expect(fn () => $this->pmb->daftarUlang($pendaftar, $this->term))
            ->toThrow(AturanAkademikException::class, 'lulus seleksi');
    });

    it('menolak meluluskan ke prodi yang tidak dipilih pendaftar', function () {
        $lain = Prodi::factory()->create();
        $pendaftar = pendaftarBaru();

        expect(fn () => $this->pmb->luluskan($pendaftar, $lain))
            ->toThrow(AturanAkademikException::class, 'bukan pilihan');
    });
});

describe('layar PMB', function () {
    beforeEach(function () {
        $this->staf = Staff::factory()->create();
        $this->staf->assignRole('baak');
        $this->actingAs($this->staf, 'staff');
    });

    it('merender daftar pendaftar', function () {
        pendaftarBaru();

        $this->get('/admin/pmb')->assertOk();
    });

    it('mendaftarkan ulang lewat layar dan menampilkan kredensial sekali', function () {
        $pendaftar = pendaftarBaru();
        $this->pmb->luluskan($pendaftar, $this->prodi);

        $this->post("/admin/pmb/{$pendaftar->uuid}/daftar-ulang", [
            'tahun_akademik_id' => $this->term->id,
        ])->assertRedirect()->assertSessionHas('kata_sandi_baru');

        expect(Mahasiswa::count())->toBe(1);
    });

    it('menolak staf tanpa izin PMB', function () {
        $keuangan = Staff::factory()->create();
        $keuangan->assignRole('keuangan');

        $this->actingAs($keuangan, 'staff')->get('/admin/pmb')->assertForbidden();
    });
});

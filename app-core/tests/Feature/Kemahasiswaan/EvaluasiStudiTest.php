<?php

declare(strict_types=1);

use App\Enums\HasilEvaluasi;
use App\Enums\KeputusanEvaluasi;
use App\Enums\SemesterType;
use App\Enums\StudentStatus;
use App\Exceptions\AturanAkademikException;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\EvaluasiStudi;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Kemahasiswaan\StatusMahasiswa;
use App\Models\Sdm\Staff;
use App\Notifications\Kemahasiswaan\PeringatanAkademik;
use App\Services\Kemahasiswaan\EvaluasiStudiService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Notification;

/**
 * Evaluasi studi.
 *
 * The load-bearing property here is a negative one: the sweep must never end
 * anybody's enrolment. Most of these tests exist to keep it that way.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->prodi = Prodi::factory()->create();
    $this->evaluasi = app(EvaluasiStudiService::class);

    $this->staf = Staff::factory()->create();
    $this->staf->assignRole('super-admin');

    /*
     * Satu semester berjalan, terpisah dari riwayat yang dievaluasi.
     *
     * Layar admin dijaga EnsureTermIsActive dan menjawab 503 tanpanya. Ini juga
     * bentuk yang sebenarnya: evaluasi dijalankan di awal semester berikutnya,
     * atas semester yang baru saja ditutup.
     */
    $this->berjalan = TahunAkademik::factory()
        ->term(2030, SemesterType::Ganjil)->berjalan()->aktif()->create();
});

/** A run of closed terms for one student, oldest first. */
function riwayatEvaluasi(Mahasiswa $mahasiswa, array $semester): TahunAkademik
{
    $terakhir = null;
    $tahun = 2024;
    $ke = 0;

    foreach ($semester as $baris) {
        $ke++;

        $term = TahunAkademik::factory()
            ->term($tahun + intdiv($ke - 1, 2), $ke % 2 === 1 ? SemesterType::Ganjil : SemesterType::Genap)
            ->terkunci()
            ->create();

        StatusMahasiswa::create([
            'mahasiswa_id' => $mahasiswa->id,
            'tahun_akademik_id' => $term->id,
            'status' => $baris['status'] ?? StudentStatus::Aktif,
            'semester_ke' => $ke,
            'sks_semester' => $baris['sks'] ?? 0,
            'sks_kumulatif' => $baris['kumulatif'] ?? 0,
            'ips' => $baris['ips'] ?? 3.00,
            'ipk' => $baris['ipk'] ?? 3.00,
            'is_final' => true,
            'finalized_at' => now(),
        ]);

        $terakhir = $term;
    }

    return $terakhir;
}

function mahasiswaEvaluasi(): Mahasiswa
{
    return Mahasiswa::factory()->create([
        'prodi_id' => test()->prodi->id,
        'status' => StudentStatus::Aktif,
    ]);
}

describe('temuan', function () {
    it('meloloskan mahasiswa yang memenuhi syarat tahap', function () {
        $mahasiswa = mahasiswaEvaluasi();

        $term = riwayatEvaluasi($mahasiswa, [
            ['kumulatif' => 20, 'ipk' => 3.20],
            ['kumulatif' => 40, 'ipk' => 3.25],
        ]);

        $this->evaluasi->jalankan($term);

        $baris = EvaluasiStudi::where('mahasiswa_id', $mahasiswa->id)
            ->where('tahap', 'Evaluasi Tahap I')->first();

        expect($baris?->temuan)->toBe(HasilEvaluasi::Lolos);
    });

    it('menandai yang di bawah syarat SKS pada tahap I', function () {
        $mahasiswa = mahasiswaEvaluasi();

        $term = riwayatEvaluasi($mahasiswa, [
            ['kumulatif' => 9, 'ipk' => 2.50],
            ['kumulatif' => 18, 'ipk' => 2.40],
        ]);

        $this->evaluasi->jalankan($term);

        $baris = EvaluasiStudi::where('tahap', 'Evaluasi Tahap I')->first();

        expect($baris?->temuan)->toBe(HasilEvaluasi::TidakMemenuhi)
            ->and($baris->syarat_sks)->toBe(24)
            ->and($baris->sks_kumulatif)->toBe(18);
    });

    it('menyebut ambang di sebelah angkanya', function () {
        // Pembaca harus bisa berselisih dengan aturannya, bukan dengan
        // mahasiswanya. "IPK 1,85" saja tidak memungkinkan itu.
        $mahasiswa = mahasiswaEvaluasi();

        $term = riwayatEvaluasi($mahasiswa, [
            ['kumulatif' => 9, 'ipk' => 1.85],
            ['kumulatif' => 18, 'ipk' => 1.85],
        ]);

        $this->evaluasi->jalankan($term);

        expect(EvaluasiStudi::where('tahap', 'Evaluasi Tahap I')->first()->alasan())
            ->toContain('dari syarat 24')
            ->toContain('dari syarat 2.00');
    });

    it('memberi peringatan ketika IPS di bawah ambang', function () {
        $mahasiswa = mahasiswaEvaluasi();

        // Satu semester saja: belum sampai tahap mana pun, tapi IPS-nya rendah.
        $term = riwayatEvaluasi($mahasiswa, [
            ['kumulatif' => 12, 'ipk' => 1.80, 'ips' => 1.80],
        ]);

        $this->evaluasi->jalankan($term);

        $baris = EvaluasiStudi::whereNull('tahap')->first();

        expect($baris?->temuan)->toBe(HasilEvaluasi::Peringatan);
    });
});

describe('semester cuti', function () {
    it('tidak menghitung cuti sebagai semester tempuh', function () {
        /*
         * Itu justru gunanya cuti. Menghitungnya menggeser titik evaluasi
         * mahasiswa satu semester lebih awal daripada yang dimaksud aturan —
         * dan menghukumnya karena sakit atau kesulitan biaya.
         */
        $mahasiswa = mahasiswaEvaluasi();

        $term = riwayatEvaluasi($mahasiswa, [
            ['kumulatif' => 20, 'ipk' => 3.20],
            ['status' => StudentStatus::Cuti, 'kumulatif' => 20, 'ipk' => 3.20],
        ]);

        $this->evaluasi->jalankan($term);

        // Dua baris kalender, tapi baru satu semester tempuh — tahap I belum
        // waktunya.
        expect(EvaluasiStudi::where('tahap', 'Evaluasi Tahap I')->count())->toBe(0);
    });

    it('mengevaluasi tahap I pada semester tempuh kedua walau ada cuti di antaranya', function () {
        $mahasiswa = mahasiswaEvaluasi();

        $term = riwayatEvaluasi($mahasiswa, [
            ['kumulatif' => 20, 'ipk' => 3.20],
            ['status' => StudentStatus::Cuti, 'kumulatif' => 20, 'ipk' => 3.20],
            ['kumulatif' => 40, 'ipk' => 3.30],
        ]);

        $this->evaluasi->jalankan($term);

        expect(EvaluasiStudi::where('tahap', 'Evaluasi Tahap I')->first()?->temuan)
            ->toBe(HasilEvaluasi::Lolos);
    });
});

describe('sapuan tidak pernah memutus', function () {
    it('tidak mengubah status mahasiswa apa pun temuannya', function () {
        /*
         * Penjaga utama modul ini. Mengakhiri studi seseorang bukan hasil yang
         * boleh dicapai pekerjaan terjadwal tanpa pengawasan.
         */
        $mahasiswa = mahasiswaEvaluasi();

        $term = riwayatEvaluasi($mahasiswa, [
            ['kumulatif' => 3, 'ipk' => 0.90, 'ips' => 0.90],
            ['kumulatif' => 6, 'ipk' => 0.85, 'ips' => 0.80],
        ]);

        $this->evaluasi->jalankan($term);

        expect($mahasiswa->refresh()->status)->toBe(StudentStatus::Aktif)
            ->and(EvaluasiStudi::where('mahasiswa_id', $mahasiswa->id)->get()
                ->every(fn ($e) => $e->keputusan === KeputusanEvaluasi::Menunggu))->toBeTrue();
    });

    it('tidak menulis ulang angka pada evaluasi yang sudah diputuskan', function () {
        /*
         * Keputusan dibuat terhadap angka sebagaimana adanya saat itu. Koreksi
         * nilai yang mendarat bulan depan tidak boleh diam-diam mengubah dasar
         * keputusan yang sudah diambil dan disampaikan.
         *
         * Diuji pada angkanya, bukan pada kolom keputusan: `updateOrCreate`
         * memang tidak menyentuh kolom keputusan, jadi memeriksa kolom itu
         * akan lulus bahkan tanpa penjaganya — tes yang tidak memaku apa pun.
         */
        $mahasiswa = mahasiswaEvaluasi();

        $term = riwayatEvaluasi($mahasiswa, [
            ['kumulatif' => 9, 'ipk' => 1.50, 'ips' => 1.50],
            ['kumulatif' => 18, 'ipk' => 1.60, 'ips' => 1.70],
        ]);

        $this->evaluasi->jalankan($term);

        $baris = EvaluasiStudi::where('tahap', 'Evaluasi Tahap I')->firstOrFail();
        $this->evaluasi->putuskan($baris, KeputusanEvaluasi::Lanjut, $this->staf, 'Cuti sakit satu semester.');

        // Koreksi nilai menaikkan capaiannya sesudah keputusan diambil.
        StatusMahasiswa::where('tahun_akademik_id', $term->id)
            ->where('mahasiswa_id', $mahasiswa->id)
            ->update(['sks_kumulatif' => 30, 'ipk' => 2.90]);

        $this->evaluasi->jalankan($term);

        expect($baris->refresh()->sks_kumulatif)->toBe(18)
            ->and((float) $baris->ipk)->toBe(1.60)
            ->and($baris->keputusan)->toBe(KeputusanEvaluasi::Lanjut);
    });

    it('melewati mahasiswa yang statusnya sudah berakhir', function () {
        // Temuan tentang orang yang sudah lulus tidak dapat ditindaklanjuti;
        // antreannya penuh derau dan kasus nyatanya tidak terlihat lagi.
        $mahasiswa = mahasiswaEvaluasi();

        $term = riwayatEvaluasi($mahasiswa, [
            ['kumulatif' => 3, 'ipk' => 0.90],
            ['kumulatif' => 6, 'ipk' => 0.85],
        ]);

        $mahasiswa->update(['status' => StudentStatus::Lulus]);

        $hasil = $this->evaluasi->jalankan($term);

        expect($hasil['dilewati'])->toBe(1)
            ->and(EvaluasiStudi::count())->toBe(0);
    });
});

describe('keputusan', function () {
    it('menolak keputusan tanpa alasan tertulis', function () {
        $mahasiswa = mahasiswaEvaluasi();
        $term = riwayatEvaluasi($mahasiswa, [['kumulatif' => 6, 'ipk' => 1.00, 'ips' => 1.00]]);

        $this->evaluasi->jalankan($term);
        $baris = EvaluasiStudi::firstOrFail();

        expect(fn () => $this->evaluasi->putuskan($baris, KeputusanEvaluasi::DropOut, $this->staf, '  '))
            ->toThrow(AturanAkademikException::class, 'alasan tertulis');
    });

    it('mengakhiri studi hanya lewat keputusan seorang staf', function () {
        $mahasiswa = mahasiswaEvaluasi();
        $term = riwayatEvaluasi($mahasiswa, [['kumulatif' => 6, 'ipk' => 1.00, 'ips' => 1.00]]);

        $this->evaluasi->jalankan($term);
        $baris = EvaluasiStudi::firstOrFail();

        $this->evaluasi->putuskan($baris, KeputusanEvaluasi::DropOut, $this->staf, 'Tiga tahap berturut gagal.');

        expect($mahasiswa->refresh()->status)->toBe(StudentStatus::DropOut)
            ->and($baris->refresh()->diputuskan_by_staff_id)->toBe($this->staf->id);
    });

    it('menolak memutuskan dua kali', function () {
        $mahasiswa = mahasiswaEvaluasi();
        $term = riwayatEvaluasi($mahasiswa, [['kumulatif' => 6, 'ipk' => 1.00, 'ips' => 1.00]]);

        $this->evaluasi->jalankan($term);
        $baris = EvaluasiStudi::firstOrFail();

        $this->evaluasi->putuskan($baris, KeputusanEvaluasi::Peringatan, $this->staf, 'Peringatan pertama.');

        expect(fn () => $this->evaluasi->putuskan(
            $baris->refresh(), KeputusanEvaluasi::Lanjut, $this->staf, 'Berubah pikiran.',
        ))->toThrow(AturanAkademikException::class, 'sudah diputuskan');
    });

    it('menyimpan alasan pembatalan tanpa menghapus catatan lama', function () {
        // Alternatifnya lebih buruk: keputusan yang salah diperbaiki dengan
        // menyunting barisnya langsung, tanpa jejak bahwa ia pernah berbunyi lain.
        $mahasiswa = mahasiswaEvaluasi();
        $term = riwayatEvaluasi($mahasiswa, [['kumulatif' => 6, 'ipk' => 1.00, 'ips' => 1.00]]);

        $this->evaluasi->jalankan($term);
        $baris = EvaluasiStudi::firstOrFail();

        $this->evaluasi->putuskan($baris, KeputusanEvaluasi::Peringatan, $this->staf, 'Peringatan pertama.');
        $this->evaluasi->batalkanKeputusan($baris->refresh(), $this->staf, 'Salah mahasiswa.');

        expect($baris->refresh()->keputusan)->toBe(KeputusanEvaluasi::Menunggu)
            ->and($baris->catatan)->toContain('Peringatan pertama.')
            ->and($baris->catatan)->toContain('Salah mahasiswa.');
    });
});

describe('pemberitahuan', function () {
    it('memberi tahu mahasiswa saat temuan dicatat, bukan menunggu keputusan', function () {
        // Nilai sebuah peringatan dini justru karena ia datang selagi mahasiswa
        // masih bisa berbuat sesuatu.
        Notification::fake();

        $mahasiswa = mahasiswaEvaluasi();
        $term = riwayatEvaluasi($mahasiswa, [['kumulatif' => 6, 'ipk' => 1.00, 'ips' => 1.00]]);

        $this->evaluasi->jalankan($term);

        Notification::assertSentTo($mahasiswa, PeringatanAkademik::class);
    });

    it('tidak memberi tahu ulang ketika sapuan dijalankan lagi', function () {
        Notification::fake();

        $mahasiswa = mahasiswaEvaluasi();
        $term = riwayatEvaluasi($mahasiswa, [['kumulatif' => 6, 'ipk' => 1.00, 'ips' => 1.00]]);

        $this->evaluasi->jalankan($term);
        $this->evaluasi->jalankan($term);

        Notification::assertSentToTimes($mahasiswa, PeringatanAkademik::class, 1);
    });

    it('tidak memberi tahu mahasiswa yang lolos', function () {
        Notification::fake();

        $mahasiswa = mahasiswaEvaluasi();

        $term = riwayatEvaluasi($mahasiswa, [
            ['kumulatif' => 20, 'ipk' => 3.20],
            ['kumulatif' => 40, 'ipk' => 3.25],
        ]);

        $this->evaluasi->jalankan($term);

        Notification::assertNothingSentTo($mahasiswa);
    });
});

describe('layar', function () {
    it('menampilkan antrean beserta aturan yang berlaku', function () {
        $mahasiswa = mahasiswaEvaluasi();
        $term = riwayatEvaluasi($mahasiswa, [['kumulatif' => 6, 'ipk' => 1.00, 'ips' => 1.00]]);

        $this->evaluasi->jalankan($term);

        $this->actingAs($this->staf, 'staff')
            ->get('/admin/evaluasi-studi')
            ->assertOk()
            ->assertSee($mahasiswa->nama)
            ->assertSee('Evaluasi Tahap I')
            ->assertSee('Semester cuti tidak dihitung', false);
    });

    it('menolak staf tanpa izin kelola mencatat keputusan', function () {
        $mahasiswa = mahasiswaEvaluasi();
        $term = riwayatEvaluasi($mahasiswa, [['kumulatif' => 6, 'ipk' => 1.00, 'ips' => 1.00]]);

        $this->evaluasi->jalankan($term);

        $keuangan = Staff::factory()->create();
        $keuangan->assignRole('keuangan');

        $this->actingAs($keuangan, 'staff')
            ->post('/admin/evaluasi-studi/'.EvaluasiStudi::firstOrFail()->uuid.'/putuskan', [
                'keputusan' => KeputusanEvaluasi::DropOut->value,
                'catatan' => 'Coba-coba.',
            ])
            ->assertForbidden();
    });
});

<?php

declare(strict_types=1);

use App\Exceptions\AturanAkademikException;
use App\Models\Akademik\KelasKuliah;
use App\Models\Akademik\Krs;
use App\Models\Akademik\KrsDetail;
use App\Models\Akademik\MataKuliah;
use App\Models\Akademik\Nilai;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Kemahasiswaan\StatusMahasiswa;
use App\Models\Sdm\Staff;
use App\Services\Akademik\BatasSksCalculator;
use App\Services\Akademik\PenutupanSemesterService;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->prodi = Prodi::factory()->create();

    $this->lalu = TahunAkademik::factory()->create(['kode' => '20251', 'tahun_mulai' => 2025]);
    $this->kini = TahunAkademik::factory()->berjalan()->create([
        'kode' => '20261', 'tahun_mulai' => 2026, 'is_active' => true,
    ]);

    $this->mahasiswa = Mahasiswa::factory()->create(['prodi_id' => $this->prodi->id]);

    $this->staf = Staff::factory()->create();
    $this->staf->assignRole('baak');

    $this->penutupan = app(PenutupanSemesterService::class);
});

/** Satu mata kuliah bernilai final untuk mahasiswa pada term tertentu. */
function nilaiFinal(TahunAkademik $term, int $sks, string $huruf, float $bobot, bool $kelasFinal = true): Nilai
{
    $test = test();

    $mk = MataKuliah::factory()->create(['prodi_id' => $test->prodi->id, 'sks' => $sks]);
    $kelas = KelasKuliah::factory()->create([
        'tahun_akademik_id' => $term->id,
        'mata_kuliah_id' => $mk->id,
        'prodi_id' => $test->prodi->id,
        'sks' => $sks,
        'status_nilai' => $kelasFinal ? 'final' : 'belum',
    ]);

    $krs = Krs::firstOrCreate(
        ['mahasiswa_id' => $test->mahasiswa->id, 'tahun_akademik_id' => $term->id],
        ['semester_ke' => 1, 'batas_sks' => 24, 'total_sks' => 0, 'status' => 'disetujui'],
    );

    $detail = KrsDetail::create([
        'krs_id' => $krs->id,
        'kelas_kuliah_id' => $kelas->id,
        'sks' => $sks,
        'is_mengulang' => false,
    ]);

    return Nilai::create([
        'krs_detail_id' => $detail->id,
        'mahasiswa_id' => $test->mahasiswa->id,
        'kelas_kuliah_id' => $kelas->id,
        'nilai_angka' => $bobot * 25,
        'nilai_huruf' => $huruf,
        'bobot' => $bobot,
        'is_final' => true,
    ]);
}

function statusSemester(TahunAkademik $term): StatusMahasiswa
{
    return StatusMahasiswa::firstOrCreate(
        ['mahasiswa_id' => test()->mahasiswa->id, 'tahun_akademik_id' => $term->id],
        ['status' => 'A', 'semester_ke' => 1, 'sks_semester' => 0, 'sks_kumulatif' => 0, 'ips' => 0, 'ipk' => 0],
    );
}

describe('gerbang yang selama ini tidak pernah tertutup', function () {
    it('membuat tangga batas SKS berbasis IPS akhirnya menyala', function () {
        // Inilah inti perbaikannya. Sebelum dibekukan, BatasSksCalculator tidak
        // menemukan acuan apa pun dan setiap mahasiswa jatuh ke batas bawaan —
        // tanpa galat, tanpa jejak, selamanya.
        nilaiFinal($this->lalu, 20, 'A', 4.0);
        statusSemester($this->lalu);

        $batas = app(BatasSksCalculator::class);
        $bawaan = (int) config('academic.krs.default_credits');

        $sebelum = $batas->untuk($this->mahasiswa, $this->kini);
        expect($sebelum['batas'])->toBe($bawaan)

            // null, bukan nol: tidak ada acuan sama sekali karena tidak ada
            // catatan semester yang beku untuk dibaca.
            ->and($sebelum['ips'])->toBeNull()
            ->and($sebelum['acuan'])->toBeNull();

        $this->penutupan->tutup($this->lalu, $this->staf);

        $sesudah = $batas->untuk($this->mahasiswa->fresh(), $this->kini);

        // IPS 4,0 harus mengangkat plafonnya di atas bawaan.
        expect($sesudah['ips'])->toBe(4.0)
            ->and($sesudah['batas'])->toBeGreaterThan($bawaan)
            ->and($sesudah['batas'])->toBe($batas->dariIps(4.0));
    });

    it('menghitung ulang tepat sebelum membekukan, bukan mempercayai angka lama', function () {
        nilaiFinal($this->lalu, 10, 'A', 4.0);
        $status = statusSemester($this->lalu);

        // Angka lama sengaja dibuat keliru; koreksi nilai bisa mendarat kapan
        // saja setelah perhitungan terakhir.
        $status->update(['ips' => 1.5, 'ipk' => 1.5, 'sks_semester' => 0]);

        $this->penutupan->tutup($this->lalu, $this->staf);

        $segar = $status->fresh();
        expect((float) $segar->ips)->toBe(4.0)
            ->and((int) $segar->sks_semester)->toBe(10)
            ->and($segar->is_final)->toBeTrue()
            ->and($segar->finalized_at)->not->toBeNull();
    });
});

describe('yang terhalang', function () {
    it('tidak membekukan mahasiswa yang masih punya kelas belum final', function () {
        nilaiFinal($this->lalu, 3, 'A', 4.0, kelasFinal: true);
        nilaiFinal($this->lalu, 3, 'B', 3.0, kelasFinal: false);
        $status = statusSemester($this->lalu);

        $hasil = $this->penutupan->tutup($this->lalu, $this->staf);

        // IPS parsial yang dibekukan lebih berbahaya daripada tidak dibekukan
        // sama sekali — ia menjadi resmi dan tampak benar.
        expect($hasil['dibekukan'])->toBe(0)
            ->and($hasil['terhalang'])->toBe(1)
            ->and($status->fresh()->is_final)->toBeFalse();
    });

    it('menyebutkan mata kuliah mana yang menghalangi', function () {
        nilaiFinal($this->lalu, 3, 'B', 3.0, kelasFinal: false);
        statusSemester($this->lalu);

        $pratinjau = $this->penutupan->pratinjau($this->lalu);

        expect($pratinjau['terhalang'])->toHaveCount(1)
            ->and($pratinjau['terhalang']->first()['alasan'])->toContain('belum difinalisasi');
    });

    it('membekukan yang siap dan melewati yang belum, dalam satu putaran', function () {
        // Kampus besar selalu punya satu dosen yang telat. Menolak menutup apa
        // pun sampai kelas terakhir masuk berarti tidak ada plafon SKS yang
        // pernah diperbarui — justru kegagalan yang mau diperbaiki.
        $siap = Mahasiswa::factory()->create(['prodi_id' => $this->prodi->id]);
        StatusMahasiswa::create([
            'mahasiswa_id' => $siap->id,
            'tahun_akademik_id' => $this->lalu->id,
            'status' => 'A', 'semester_ke' => 1,
            'sks_semester' => 0, 'sks_kumulatif' => 0, 'ips' => 0, 'ipk' => 0,
        ]);

        nilaiFinal($this->lalu, 3, 'B', 3.0, kelasFinal: false);
        statusSemester($this->lalu);

        $hasil = $this->penutupan->tutup($this->lalu, $this->staf);

        expect($hasil['dibekukan'])->toBe(1)
            ->and($hasil['terhalang'])->toBe(1);
    });
});

describe('idempotensi dan pembukaan kembali', function () {
    it('melewati catatan yang sudah beku, tidak menghitungnya ulang', function () {
        nilaiFinal($this->lalu, 10, 'A', 4.0);
        statusSemester($this->lalu);

        $this->penutupan->tutup($this->lalu, $this->staf);
        $kedua = $this->penutupan->tutup($this->lalu, $this->staf);

        // Menulis ulang KHS yang sudah terbit bukan wewenang sebuah proses batch.
        expect($kedua['dibekukan'])->toBe(0)
            ->and($kedua['dilewati'])->toBe(1);
    });

    it('mewajibkan alasan saat membuka kembali', function () {
        nilaiFinal($this->lalu, 10, 'A', 4.0);
        $status = statusSemester($this->lalu);
        $this->penutupan->tutup($this->lalu, $this->staf);

        expect(fn () => $this->penutupan->bukaKembali($status->fresh(), $this->staf, ''))
            ->toThrow(AturanAkademikException::class, 'wajib disertai alasan');
    });

    it('menolak membuka catatan yang belum beku', function () {
        $status = statusSemester($this->lalu);

        expect(fn () => $this->penutupan->bukaKembali($status, $this->staf, 'Alasan yang panjang.'))
            ->toThrow(AturanAkademikException::class, 'belum dibekukan');
    });

    it('mengembalikan batas SKS ke bawaan saat catatan dibuka kembali', function () {
        nilaiFinal($this->lalu, 20, 'A', 4.0);
        $status = statusSemester($this->lalu);
        $this->penutupan->tutup($this->lalu, $this->staf);

        $this->penutupan->bukaKembali($status->fresh(), $this->staf, 'Ada koreksi nilai yang tertunda.');

        $batas = app(BatasSksCalculator::class)->untuk($this->mahasiswa->fresh(), $this->kini);

        expect($batas['batas'])->toBe((int) config('academic.krs.default_credits'));
    });
});

describe('layar penutupan semester', function () {
    it('merender layar', function () {
        nilaiFinal($this->lalu, 10, 'A', 4.0);
        statusSemester($this->lalu);

        $this->actingAs($this->staf, 'staff')
            ->get('/admin/tutup-semester?term='.$this->lalu->id)
            ->assertOk();
    });

    it('membekukan lewat layar', function () {
        nilaiFinal($this->lalu, 10, 'A', 4.0);
        $status = statusSemester($this->lalu);

        $this->actingAs($this->staf, 'staff')
            ->post('/admin/tutup-semester', ['tahun_akademik_id' => $this->lalu->id])
            ->assertRedirect()
            ->assertSessionHas('sukses');

        expect($status->fresh()->is_final)->toBeTrue();
    });

    it('memperingatkan bila ada yang terhalang', function () {
        nilaiFinal($this->lalu, 3, 'B', 3.0, kelasFinal: false);
        statusSemester($this->lalu);

        $this->actingAs($this->staf, 'staff')
            ->post('/admin/tutup-semester', ['tahun_akademik_id' => $this->lalu->id])
            ->assertSessionHas('peringatan');
    });

    it('mewajibkan alasan minimal pada pembukaan kembali lewat layar', function () {
        nilaiFinal($this->lalu, 10, 'A', 4.0);
        $status = statusSemester($this->lalu);
        $this->penutupan->tutup($this->lalu, $this->staf);

        $this->actingAs($this->staf, 'staff')
            ->post("/admin/tutup-semester/{$status->uuid}/buka", ['alasan' => 'pendek'])
            ->assertSessionHasErrors('alasan');

        expect($status->fresh()->is_final)->toBeTrue();
    });

    it('menolak staf tanpa izin master', function () {
        $keuangan = Staff::factory()->create();
        $keuangan->assignRole('keuangan');

        $this->actingAs($keuangan, 'staff')->get('/admin/tutup-semester')->assertForbidden();
    });
});

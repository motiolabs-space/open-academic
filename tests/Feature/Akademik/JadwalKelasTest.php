<?php

declare(strict_types=1);

use App\Exceptions\AturanAkademikException;
use App\Models\Akademik\Gedung;
use App\Models\Akademik\KelasKuliah;
use App\Models\Akademik\Krs;
use App\Models\Akademik\KrsDetail;
use App\Models\Akademik\Kurikulum;
use App\Models\Akademik\MataKuliah;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\Ruang;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\Staff;
use App\Services\Akademik\JadwalService;
use App\Services\Akademik\KelasKuliahService;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->term = TahunAkademik::factory()->berjalan()->create(['is_active' => true]);
    $this->prodi = Prodi::factory()->create();

    $gedung = Gedung::create(['kode' => 'A', 'nama' => 'Gedung A']);
    $this->ruang = Ruang::create([
        'gedung_id' => $gedung->id, 'kode' => 'A-101', 'nama' => 'Ruang 101',
        'kapasitas' => 40, 'jenis' => 'kelas', 'is_active' => true,
    ]);

    $this->staf = Staff::factory()->create();
    $this->staf->assignRole('baak');

    $this->kelasService = app(KelasKuliahService::class);
    $this->jadwalService = app(JadwalService::class);
});

function bukaKelas(string $kodeMk = 'IF101', string $kodeKelas = 'A', int $kuota = 40): KelasKuliah
{
    $test = test();

    $mk = MataKuliah::factory()->create([
        'prodi_id' => $test->prodi->id,
        'kode' => $kodeMk,
        'sks' => 3,
    ]);

    return $test->kelasService->buka($test->term, $mk, $kodeKelas, $kuota);
}

describe('membuka kelas', function () {
    it('memotret SKS dari mata kuliah, bukan mereferensikannya', function () {
        $mk = MataKuliah::factory()->create(['prodi_id' => $this->prodi->id, 'sks' => 3]);
        $kelas = $this->kelasService->buka($this->term, $mk, 'A', 40);

        // Revisi kurikulum yang mengubah bobot MK tidak boleh mengubah dasar
        // penilaian mahasiswa yang sudah terlanjur mengambilnya.
        $mk->update(['sks' => 4]);

        expect($kelas->fresh()->sks)->toBe(3);
    });

    it('membuka beberapa kelas paralel berkode A, B, C', function () {
        $mk = MataKuliah::factory()->create(['prodi_id' => $this->prodi->id]);

        $dibuat = $this->kelasService->bukaParalel($this->term, $mk, 3, 40);

        expect($dibuat->pluck('kode')->all())->toBe(['A', 'B', 'C']);
    });

    it('melanjutkan kode yang belum terpakai saat menambah paralel', function () {
        $mk = MataKuliah::factory()->create(['prodi_id' => $this->prodi->id]);
        $this->kelasService->bukaParalel($this->term, $mk, 2, 40);

        $lagi = $this->kelasService->bukaParalel($this->term, $mk, 2, 40);

        expect($lagi->pluck('kode')->all())->toBe(['C', 'D']);
    });

    it('menolak membuka kelas pada semester yang sudah dikunci', function () {
        $terkunci = TahunAkademik::factory()->create(['kode' => '20252', 'is_locked' => true]);
        $mk = MataKuliah::factory()->create(['prodi_id' => $this->prodi->id]);

        expect(fn () => $this->kelasService->buka($terkunci, $mk, 'A', 40))
            ->toThrow(AturanAkademikException::class, 'dikunci');
    });
});

describe('bentrok yang menghalangi', function () {
    it('menolak dua kelas pada ruang dan jam yang sama', function () {
        $a = bukaKelas('IF101');
        $b = bukaKelas('IF102');

        $this->jadwalService->jadwalkan($a, 1, '08:00', '09:40', $this->ruang->id);

        // Satu ruang tidak bisa menampung dua kelas sekaligus — tidak ada
        // kebijakan kampus yang membuat ini bisa dijalankan.
        expect(fn () => $this->jadwalService->jadwalkan($b, 1, '09:00', '10:40', $this->ruang->id))
            ->toThrow(AturanAkademikException::class, 'sudah dipakai');
    });

    it('mengizinkan jadwal berurutan yang ujungnya bersentuhan', function () {
        $a = bukaKelas('IF101');
        $b = bukaKelas('IF102');

        $this->jadwalService->jadwalkan($a, 1, '08:00', '10:00', $this->ruang->id);

        // 10:00–12:00 setelah 08:00–10:00 adalah berurutan, bukan bersamaan.
        $hasil = $this->jadwalService->jadwalkan($b, 1, '10:00', '12:00', $this->ruang->id);

        expect($hasil['jadwal'])->not->toBeNull();
    });

    it('mengizinkan jam sama di hari berbeda', function () {
        $a = bukaKelas('IF101');
        $b = bukaKelas('IF102');

        $this->jadwalService->jadwalkan($a, 1, '08:00', '09:40', $this->ruang->id);
        $hasil = $this->jadwalService->jadwalkan($b, 2, '08:00', '09:40', $this->ruang->id);

        expect($hasil['jadwal'])->not->toBeNull();
    });

    it('menolak satu dosen mengajar dua kelas pada jam yang sama', function () {
        $dosen = Dosen::factory()->create();
        $a = bukaKelas('IF101');
        $b = bukaKelas('IF102');

        $this->kelasService->tugaskanDosen($a, $dosen);
        $this->kelasService->tugaskanDosen($b, $dosen);

        $this->jadwalService->jadwalkan($a, 3, '13:00', '14:40', $this->ruang->id);

        $ruangLain = Ruang::create([
            'gedung_id' => $this->ruang->gedung_id, 'kode' => 'A-102', 'nama' => 'Ruang 102',
            'kapasitas' => 40, 'jenis' => 'kelas', 'is_active' => true,
        ]);

        expect(fn () => $this->jadwalService->jadwalkan($b, 3, '14:00', '15:40', $ruangLain->id))
            ->toThrow(AturanAkademikException::class, 'sudah mengajar');
    });

    it('menolak menugaskan dosen yang sudah mengajar pada slot yang sama', function () {
        $dosen = Dosen::factory()->create();
        $a = bukaKelas('IF101');
        $b = bukaKelas('IF102');

        $this->kelasService->tugaskanDosen($a, $dosen);
        $this->jadwalService->jadwalkan($a, 4, '08:00', '09:40', $this->ruang->id);
        $this->jadwalService->jadwalkan($b, 4, '08:00', '09:40', null);

        // Menugaskan lebih dulu lalu menjadwalkan adalah cara dosen berakhir di
        // dua ruang sekaligus; pemeriksaannya harus jalan di kedua arah.
        expect(fn () => $this->kelasService->tugaskanDosen($b, $dosen))
            ->toThrow(AturanAkademikException::class, 'sudah mengajar');
    });

    it('menolak menugaskan dosen nonaktif', function () {
        $dosen = Dosen::factory()->create(['is_active' => false]);

        expect(fn () => $this->kelasService->tugaskanDosen(bukaKelas(), $dosen))
            ->toThrow(AturanAkademikException::class, 'nonaktif');
    });
});

describe('peringatan yang tidak menghalangi', function () {
    it('memperingatkan kuota melebihi kapasitas ruang, tapi tetap menyimpan', function () {
        $kelas = bukaKelas('IF101', 'A', 60);

        $hasil = $this->jadwalService->jadwalkan($kelas, 1, '08:00', '09:40', $this->ruang->id);

        expect($hasil['jadwal'])->not->toBeNull()
            ->and($hasil['peringatan']->pluck('jenis'))->toContain('kapasitas');
    });

    it('memperingatkan bentrok sekohor, tapi tetap menyimpan', function () {
        // Mata kuliah pilihan memang lazim bertabrakan; memblokirnya membuat
        // penjadwalan dikerjakan di luar sistem.
        $kurikulum = Kurikulum::factory()->create(['prodi_id' => $this->prodi->id, 'is_active' => true]);

        $mkA = MataKuliah::factory()->create(['prodi_id' => $this->prodi->id, 'kode' => 'IF201']);
        $mkB = MataKuliah::factory()->create(['prodi_id' => $this->prodi->id, 'kode' => 'IF202']);

        foreach ([$mkA, $mkB] as $mk) {
            $kurikulum->mataKuliah()->attach($mk->id, ['semester' => 3, 'jenis' => 'wajib']);
        }

        $a = $this->kelasService->buka($this->term, $mkA, 'A', 40);
        $b = $this->kelasService->buka($this->term, $mkB, 'A', 40);

        $this->jadwalService->jadwalkan($a, 2, '08:00', '09:40', $this->ruang->id);

        $ruangLain = Ruang::create([
            'gedung_id' => $this->ruang->gedung_id, 'kode' => 'A-103', 'nama' => 'Ruang 103',
            'kapasitas' => 40, 'jenis' => 'kelas', 'is_active' => true,
        ]);

        $hasil = $this->jadwalService->jadwalkan($b, 2, '08:00', '09:40', $ruangLain->id);

        expect($hasil['jadwal'])->not->toBeNull()
            ->and($hasil['peringatan']->pluck('jenis'))->toContain('kohor');
    });
});

describe('kuota dan penghapusan', function () {
    it('menolak menurunkan kuota di bawah jumlah yang sudah terdaftar', function () {
        $kelas = bukaKelas();
        $kelas->update(['terisi' => 25]);

        expect(fn () => $this->kelasService->perbarui($kelas->fresh(), ['kuota' => 20]))
            ->toThrow(AturanAkademikException::class, 'tidak dapat diturunkan');
    });

    it('menolak menghapus kelas yang sudah diambil mahasiswa', function () {
        $kelas = bukaKelas();
        $mahasiswa = Mahasiswa::factory()->create(['prodi_id' => $this->prodi->id]);
        $krs = Krs::factory()->create([
            'mahasiswa_id' => $mahasiswa->id,
            'tahun_akademik_id' => $this->term->id,
        ]);
        KrsDetail::create([
            'krs_id' => $krs->id,
            'kelas_kuliah_id' => $kelas->id,
            'sks' => 3,
            'is_mengulang' => false,
        ]);

        expect(fn () => $this->kelasService->tutup($kelas))
            ->toThrow(AturanAkademikException::class, 'sudah diambil mahasiswa');
    });

    it('menghapus kelas kosong beserta jadwal dan penugasannya', function () {
        $kelas = bukaKelas();
        $this->kelasService->tugaskanDosen($kelas, Dosen::factory()->create());
        $this->jadwalService->jadwalkan($kelas, 1, '08:00', '09:40', $this->ruang->id);

        $this->kelasService->tutup($kelas);

        expect(KelasKuliah::whereKey($kelas->id)->exists())->toBeFalse()
            ->and(DB::table('jadwal_kuliah')->whereNull('deleted_at')->where('kelas_kuliah_id', $kelas->id)->count())->toBe(0)
            ->and(DB::table('kelas_dosen')->where('kelas_kuliah_id', $kelas->id)->count())->toBe(0);
    });
});

describe('layar jadwal & kelas', function () {
    it('merender daftar kelas', function () {
        bukaKelas();

        $this->actingAs($this->staf, 'staff')->get('/admin/kelas')->assertOk();
    });

    it('membuka kelas lewat layar', function () {
        $mk = MataKuliah::factory()->create(['prodi_id' => $this->prodi->id]);

        $this->actingAs($this->staf, 'staff')->post('/admin/kelas', [
            'tahun_akademik_id' => $this->term->id,
            'mata_kuliah_id' => $mk->id,
            'jumlah_kelas' => 2,
            'kuota' => 40,
            'mode' => 'tatap_muka',
        ])->assertRedirect()->assertSessionHas('sukses');

        expect(KelasKuliah::where('mata_kuliah_id', $mk->id)->count())->toBe(2);
    });

    it('menolak menghapus slot jadwal milik kelas lain', function () {
        $a = bukaKelas('IF101');
        $b = bukaKelas('IF102');

        $slotB = $this->jadwalService->jadwalkan($b, 5, '08:00', '09:40', $this->ruang->id)['jadwal'];

        // Kedua parameter rute diselesaikan terpisah; tanpa pemeriksaan
        // keterkaitan, slot kelas lain bisa dihapus lewat URL kelas ini.
        $this->actingAs($this->staf, 'staff')
            ->delete("/admin/kelas/{$a->uuid}/jadwal/{$slotB->uuid}")
            ->assertNotFound();

        expect($slotB->fresh())->not->toBeNull();
    });

    it('menolak staf tanpa izin kelas', function () {
        $keuangan = Staff::factory()->create();
        $keuangan->assignRole('keuangan');

        $this->actingAs($keuangan, 'staff')->get('/admin/kelas')->assertForbidden();
    });
});

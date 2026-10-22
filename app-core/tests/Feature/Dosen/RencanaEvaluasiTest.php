<?php

declare(strict_types=1);

use App\Enums\JenisEvaluasi;
use App\Enums\SemesterType;
use App\Exceptions\PenilaianException;
use App\Models\Akademik\KelasKuliah;
use App\Models\Akademik\KomponenNilai;
use App\Models\Akademik\Krs;
use App\Models\Akademik\KrsDetail;
use App\Models\Akademik\MataKuliah;
use App\Models\Akademik\NilaiKomponen;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Dosen;
use App\Services\Akademik\PenilaianService;
use Database\Seeders\RolePermissionSeeder;

/**
 * The screen where a lecturer composes the class evaluation plan — name,
 * weight and *kind* per component. The kind is what PDDIKTI classifies and
 * what decides whether a case-method class is recognised for IKU 7; until
 * this screen existed, components could only come from the defaults.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->term = TahunAkademik::factory()->term(2026, SemesterType::Ganjil)->berjalan()->aktif()->create([
        'nilai_mulai' => now()->subMonth(),
        'nilai_selesai' => now()->addMonth(),
    ]);
    $this->prodi = Prodi::factory()->create();
    $this->dosen = Dosen::factory()->create(['prodi_id' => $this->prodi->id]);
    $this->dosen->assignRole('dosen');

    $this->kelas = KelasKuliah::factory()->create([
        'tahun_akademik_id' => $this->term->id,
        'mata_kuliah_id' => MataKuliah::factory()->create(['prodi_id' => $this->prodi->id])->id,
        'prodi_id' => $this->prodi->id,
        'sks' => 3,
    ]);
    $this->kelas->dosen()->attach($this->dosen->id, ['peran' => 'pengampu']);

    $this->penilaian = app(PenilaianService::class);
});

function kirimRencana(array $komponen)
{
    return test()->actingAs(test()->dosen, 'dosen')
        ->post('/dosen/nilai/'.test()->kelas->uuid.'/rencana', ['komponen' => $komponen]);
}

function komponenBerisiNilai(KelasKuliah $kelas, string $nama, int $bobot, string $jenis): KomponenNilai
{
    $komponen = KomponenNilai::create([
        'kelas_kuliah_id' => $kelas->id, 'nama' => $nama, 'bobot' => $bobot, 'jenis_evaluasi' => $jenis, 'urutan' => 0,
    ]);

    $mahasiswa = Mahasiswa::factory()->create(['prodi_id' => $kelas->prodi_id]);
    $krs = Krs::create(['mahasiswa_id' => $mahasiswa->id, 'tahun_akademik_id' => $kelas->tahun_akademik_id, 'semester_ke' => 3, 'batas_sks' => 24, 'status' => 'disetujui']);
    $detail = KrsDetail::create(['krs_id' => $krs->id, 'kelas_kuliah_id' => $kelas->id, 'sks' => 3]);
    NilaiKomponen::create(['komponen_nilai_id' => $komponen->id, 'krs_detail_id' => $detail->id, 'nilai' => 80]);

    return $komponen;
}

describe('layar', function () {
    it('menampilkan rencana evaluasi dengan jenis tiap komponen dan pilihan jenisnya', function () {
        $this->actingAs($this->dosen, 'dosen')
            ->get("/dosen/nilai/{$this->kelas->uuid}")
            ->assertOk()
            ->assertSee('Rencana Evaluasi')
            ->assertSee('Aktivitas Partisipatif')
            ->assertSee('Simpan Rencana');
    });

    it('menyimpan rencana baru lewat layar, dengan jenis per komponen', function () {
        $this->penilaian->komponen($this->kelas); // bawaan: Tugas/UTS/UAS

        kirimRencana([
            ['nama' => 'Diskusi kasus', 'bobot' => 30, 'jenis' => 'partisipatif'],
            ['nama' => 'Proyek tim', 'bobot' => 30, 'jenis' => 'proyek'],
            ['nama' => 'UAS', 'bobot' => 40, 'jenis' => 'uas'],
        ])->assertRedirect()->assertSessionHas('sukses');

        $komponen = $this->kelas->komponenNilai()->orderBy('urutan')->get();

        expect($komponen->pluck('nama')->all())->toBe(['Diskusi kasus', 'Proyek tim', 'UAS'])
            ->and($komponen->pluck('jenis_evaluasi')->all())->toBe([JenisEvaluasi::Partisipatif, JenisEvaluasi::Proyek, JenisEvaluasi::Uas])
            ->and($komponen->sum('bobot'))->toBe(100);
    });

    it('menolak bobot yang tidak berjumlah 100 dan menyebut angkanya', function () {
        kirimRencana([
            ['nama' => 'UTS', 'bobot' => 40, 'jenis' => 'uts'],
            ['nama' => 'UAS', 'bobot' => 40, 'jenis' => 'uas'],
        ])->assertSessionHasErrors('komponen');

        expect(session('errors')->first('komponen'))->toContain('80%');
    });

    it('menolak komponen tanpa jenis — jenis bukan pilihan, PDDIKTI mewajibkannya', function () {
        kirimRencana([
            ['nama' => 'UTS', 'bobot' => 50, 'jenis' => ''],
            ['nama' => 'UAS', 'bobot' => 50, 'jenis' => 'uas'],
        ])->assertSessionHasErrors('komponen.0.jenis');
    });

    it('menolak nama komponen yang berulang', function () {
        kirimRencana([
            ['nama' => 'Tugas', 'bobot' => 50, 'jenis' => 'tugas'],
            ['nama' => 'tugas', 'bobot' => 50, 'jenis' => 'tugas'],
        ])->assertSessionHasErrors('komponen.0.nama');
    });

    it('memberi tahu kelas kolaboratif yang rencananya belum memenuhi ambang IKU 7', function () {
        $this->kelas->update(['is_case_method' => true]);

        kirimRencana([
            ['nama' => 'Diskusi', 'bobot' => 30, 'jenis' => 'partisipatif'],
            ['nama' => 'UAS', 'bobot' => 70, 'jenis' => 'uas'],
        ])->assertSessionHas('peringatan', fn (string $pesan): bool => str_contains($pesan, '30%') && str_contains($pesan, '50%'));
    });

    it('mengonfirmasi kelas kolaboratif yang memenuhi ambang', function () {
        $this->kelas->update(['is_team_based_project' => true]);

        kirimRencana([
            ['nama' => 'Proyek', 'bobot' => 60, 'jenis' => 'proyek'],
            ['nama' => 'UAS', 'bobot' => 40, 'jenis' => 'uas'],
        ])->assertSessionHas('sukses', fn (string $pesan): bool => str_contains($pesan, 'memenuhi syarat IKU 7'));
    });

    it('menolak dosen yang tidak mengampu kelas itu', function () {
        $lain = Dosen::factory()->create(['prodi_id' => $this->prodi->id]);
        $lain->assignRole('dosen');

        $this->actingAs($lain, 'dosen')
            ->post("/dosen/nilai/{$this->kelas->uuid}/rencana", ['komponen' => [['nama' => 'UAS', 'bobot' => 100, 'jenis' => 'uas']]])
            ->assertForbidden();
    });
});

describe('nilai yang sudah tercatat', function () {
    it('mengubah komponen yang berisi nilai di tempat, tanpa kehilangan nilainya', function () {
        $uts = komponenBerisiNilai($this->kelas, 'UTS', 100, 'uts');

        $this->penilaian->simpanKomponen($this->kelas, [
            ['id' => $uts->id, 'nama' => 'Ujian Tengah', 'bobot' => 60, 'jenis' => 'uts'],
            ['nama' => 'UAS', 'bobot' => 40, 'jenis' => 'uas'],
        ]);

        expect($uts->fresh()->nama)->toBe('Ujian Tengah')
            ->and($uts->fresh()->bobot)->toBe(60)
            ->and(NilaiKomponen::where('komponen_nilai_id', $uts->id)->count())->toBe(1)
            ->and($this->kelas->komponenNilai()->count())->toBe(2);
    });

    it('menolak menghapus komponen yang berisi nilai, dan menyebut namanya', function () {
        $uts = komponenBerisiNilai($this->kelas, 'UTS', 100, 'uts');

        expect(fn () => $this->penilaian->simpanKomponen($this->kelas, [
            ['nama' => 'UAS', 'bobot' => 100, 'jenis' => 'uas'],
        ]))->toThrow(PenilaianException::class, '"UTS" sudah berisi nilai');

        expect($uts->fresh())->not->toBeNull()
            ->and(NilaiKomponen::count())->toBe(1);
    });

    it('menghapus komponen kosong yang tidak lagi ada di rencana', function () {
        $this->penilaian->komponen($this->kelas); // Tugas/UTS/UAS, tanpa nilai

        $this->penilaian->simpanKomponen($this->kelas, [
            ['nama' => 'UAS', 'bobot' => 100, 'jenis' => 'uas'],
        ]);

        expect($this->kelas->komponenNilai()->pluck('nama')->all())->toBe(['UAS']);
    });

    it('menandai komponen berisi nilai di layar agar tombol hapusnya tidak muncul', function () {
        komponenBerisiNilai($this->kelas, 'UTS', 100, 'uts');

        $html = $this->actingAs($this->dosen, 'dosen')
            ->get("/dosen/nilai/{$this->kelas->uuid}")
            ->assertOk()
            ->getContent();

        // @js() escapes the quotes around keys; match the marker either way.
        expect(preg_match('/bernilai(\\\\u0022|"):true/', $html))->toBe(1);
    });
});

describe('penguncian', function () {
    it('menolak mengubah rencana kelas yang nilainya sudah final', function () {
        $this->penilaian->komponen($this->kelas);
        $this->kelas->update(['status_nilai' => 'final', 'finalized_at' => now()]);

        kirimRencana([['nama' => 'UAS', 'bobot' => 100, 'jenis' => 'uas']])
            ->assertRedirect()
            ->assertSessionHas('galat');

        expect($this->kelas->komponenNilai()->count())->toBe(3);
    });
});

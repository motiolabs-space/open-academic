<?php

declare(strict_types=1);

use App\DTOs\Akademik\PemetaanCapaianData;
use App\Exceptions\AturanAkademikException;
use App\Models\Akademik\CapaianCplMahasiswa;
use App\Models\Akademik\CapaianCpmkMahasiswa;
use App\Models\Akademik\KelasKuliah;
use App\Models\Akademik\KomponenNilai;
use App\Models\Akademik\MataKuliah;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\Staff;
use App\Services\Akademik\CapaianService;
use App\Services\Akademik\CpmkService;
use App\Services\Akademik\PenilaianService;
use App\Services\Akademik\RekapCapaianService;
use App\Services\Akademik\RpsService;
use App\Services\Lkps\PerakitBorang;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Tests\Support\KampusObe;

/**
 * Reading stored attainment back on three portals, the RPS sharing the same
 * CPMK, and the LKPS table that quotes it (ADR-038 §5–§6).
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->k = KampusObe::bangun();
    $this->rekap = app(RekapCapaianService::class);

    app(CapaianService::class)->petakan($this->k->kelas, new PemetaanCapaianData($this->k->pemetaanBaku()));
});

/** Finalises the fixture class with the given students' scores. */
function finalkanKelasObe(KampusObe $k, array $daftarSkor): array
{
    $detail = array_map(fn (array $skor) => $k->peserta($skor), $daftarSkor);

    app(PenilaianService::class)->finalisasi($k->kelas, $k->dosen);

    return $detail;
}

function kueriRekapObe(Closure $panggil): int
{
    auth()->forgetGuards();
    DB::flushQueryLog();
    DB::enableQueryLog();

    try {
        $panggil();
    } finally {
        $jumlah = count(array_filter(
            DB::getRawQueryLog(),
            fn (array $q): bool => preg_match('/sqlite_master|pragma_table_xinfo/', $q['raw_query']) !== 1,
        ));
        DB::disableQueryLog();
    }

    return $jumlah;
}

describe('rekap kelas', function () {
    it('membaca angka tersimpan per mahasiswa dan meringkasnya', function () {
        finalkanKelasObe($this->k, [
            ['Tugas' => 80, 'UTS' => 60, 'UAS' => 90],
            ['Tugas' => 40, 'UTS' => 50, 'UAS' => 60],
        ]);

        $rekap = $this->rekap->kelas($this->k->kelas);

        // Second student: CPMK-1 = (40 + 50) / 2 = 45, below the 65 threshold.
        expect($rekap['terhitung'])->toBeTrue()
            ->and($rekap['cpmk']->pluck('kode')->all())->toBe(['CPMK-1', 'CPMK-2', 'CPMK-3'])
            ->and($rekap['ringkas_cpmk'][$this->k->cpmkId('CPMK-1')])->toBe(['rerata' => 57.5, 'jumlah' => 2, 'tercapai' => 1])
            ->and($rekap['ringkas_cpl'][$this->k->cplB->id]['jumlah'])->toBe(2);
    });

    it('membuka rekap untuk dosen pengampu dan staf, menolak dosen lain', function () {
        finalkanKelasObe($this->k, [['Tugas' => 80, 'UTS' => 60, 'UAS' => 90]]);

        $this->actingAs($this->k->dosen, 'dosen')->get(route('dosen.capaian.kelas', $this->k->kelas))
            ->assertOk()->assertSee('CPMK-2')->assertSee('73,2');

        $lain = Dosen::factory()->create();
        $lain->assignRole('dosen');
        $this->actingAs($lain, 'dosen')->get(route('dosen.capaian.kelas', $this->k->kelas))->assertForbidden();

        // Another portal's session does not open the lecturer's screen.
        $this->actingAs($this->k->staf, 'staff')->get(route('dosen.capaian.kelas', $this->k->kelas))->assertForbidden();
    });

    it('mengekspor CSV dengan koma desimal dan sel rumus yang dinetralkan', function () {
        $m = Mahasiswa::factory()->create(['prodi_id' => $this->k->prodi->id, 'nama' => '=HYPERLINK("x")']);
        $this->k->peserta(['Tugas' => 80, 'UTS' => 60, 'UAS' => 90], $m);
        app(PenilaianService::class)->finalisasi($this->k->kelas, $this->k->dosen);

        $isi = $this->actingAs($this->k->dosen, 'dosen')
            ->get(route('dosen.capaian.ekspor', $this->k->kelas))
            ->assertOk()
            ->streamedContent();

        expect($isi)->toContain('CPMK-1')->toContain('CPL-A')
            ->toContain('70,00')->toContain('73,20')
            ->toContain("'=HYPERLINK");
    });

    it('menjaga jumlah kueri rekap kelas tetap saat peserta bertambah', function () {
        finalkanKelasObe($this->k, [
            ['Tugas' => 80, 'UTS' => 60, 'UAS' => 90],
            ['Tugas' => 70, 'UTS' => 70, 'UAS' => 70],
        ]);

        Model::preventLazyLoading();
        $buka = fn () => kueriRekapObe(fn () => $this->actingAs($this->k->dosen, 'dosen')
            ->get(route('dosen.capaian.kelas', $this->k->kelas))->assertOk());

        $buka();
        $sedikit = $buka();

        // A second class of the same course, finalised with six students,
        // grows the tables the screen reads from without touching this class.
        $kelas = $this->k->kelas;
        $this->k->kelas->update(['status_nilai' => 'sebagian']);
        foreach (range(1, 6) as $i) {
            $this->k->peserta(['Tugas' => 60 + $i, 'UTS' => 70, 'UAS' => 80]);
        }
        CapaianCpmkMahasiswa::where('kelas_kuliah_id', $kelas->id)->delete();
        CapaianCplMahasiswa::where('kelas_kuliah_id', $kelas->id)->delete();
        app(PenilaianService::class)->finalisasi($kelas->fresh(), $this->k->dosen);

        $banyak = $buka();
        Model::preventLazyLoading(false);

        expect($banyak)->toBe($sedikit)->and($sedikit)->toBeLessThanOrEqual(14);
    });
});

describe('rekap prodi per angkatan', function () {
    it('membobot capaian CPL lintas mata kuliah dengan SKS', function () {
        [$detail] = finalkanKelasObe($this->k, [['Tugas' => 80, 'UTS' => 60, 'UAS' => 90]]);
        $mahasiswa = $detail->krs->mahasiswa;

        // A one-credit course where the same student reached CPL-A at 60.
        $mk = MataKuliah::factory()->create(['prodi_id' => $this->k->prodi->id, 'sks' => 1]);
        $kelas = KelasKuliah::factory()->create([
            'tahun_akademik_id' => $this->k->term->id, 'mata_kuliah_id' => $mk->id,
            'prodi_id' => $this->k->prodi->id, 'sks' => 1, 'status_nilai' => 'final',
        ]);
        $baris = $this->k->peserta([], $mahasiswa, $kelas);
        CapaianCplMahasiswa::create([
            'krs_detail_id' => $baris->id, 'mahasiswa_id' => $mahasiswa->id, 'kelas_kuliah_id' => $kelas->id,
            'prodi_cpl_id' => $this->k->cplA->id, 'nilai' => 60, 'sks' => 1,
        ]);

        $hasil = $this->rekap->prodi($this->k->prodi);

        // (73,2 × 3 + 60 × 1) / 4 = 69,9
        expect($hasil['mahasiswa']->first()['cpl'][$this->k->cplA->id])->toBe(69.9)
            ->and($hasil['angkatan'])->toBe([2024]);
    });

    it('memfilter angkatan', function () {
        $lama = Mahasiswa::factory()->create(['prodi_id' => $this->k->prodi->id, 'angkatan' => 2022]);
        $this->k->peserta(['Tugas' => 80, 'UTS' => 60, 'UAS' => 90], $lama);
        $this->k->peserta(['Tugas' => 50, 'UTS' => 50, 'UAS' => 50]);
        app(PenilaianService::class)->finalisasi($this->k->kelas, $this->k->dosen);

        expect($this->rekap->prodi($this->k->prodi, 2022)['mahasiswa'])->toHaveCount(1)
            ->and($this->rekap->prodi($this->k->prodi)['mahasiswa'])->toHaveCount(2)
            ->and($this->rekap->prodi($this->k->prodi)['angkatan'])->toBe([2024, 2022]);
    });

    it('membuka layar dan CSV hanya untuk staf ber-nilai.view', function () {
        finalkanKelasObe($this->k, [['Tugas' => 80, 'UTS' => 60, 'UAS' => 90]]);

        $this->actingAs($this->k->staf, 'staff')
            ->get(route('admin.capaian', ['prodi' => $this->k->prodi->uuid]))
            ->assertOk()->assertSee('CPL-A')->assertSee('73,2');

        expect($this->actingAs($this->k->staf, 'staff')
            ->get(route('admin.capaian.ekspor', ['prodi' => $this->k->prodi->uuid, 'angkatan' => 2024]))
            ->assertOk()->streamedContent())->toContain('73,20');

        $keuangan = Staff::factory()->create();
        $keuangan->assignRole('keuangan');

        $this->actingAs($keuangan, 'staff')->get(route('admin.capaian'))->assertForbidden();
        $this->actingAs($keuangan, 'staff')->get(route('admin.capaian.ekspor'))->assertForbidden();
    });

    it('menjaga jumlah kueri rekap prodi tetap saat mahasiswa bertambah', function () {
        finalkanKelasObe($this->k, [['Tugas' => 80, 'UTS' => 60, 'UAS' => 90]]);

        Model::preventLazyLoading();
        $buka = fn () => kueriRekapObe(fn () => $this->actingAs($this->k->staf, 'staff')
            ->get(route('admin.capaian', ['prodi' => $this->k->prodi->uuid]))->assertOk());

        $buka();
        $sedikit = $buka();

        $kelas = KelasKuliah::factory()->create([
            'tahun_akademik_id' => $this->k->term->id, 'mata_kuliah_id' => $this->k->mk->id,
            'prodi_id' => $this->k->prodi->id, 'sks' => 3,
        ]);
        foreach ($this->k->komponen as $nama => $komponen) {
            KomponenNilai::create(['kelas_kuliah_id' => $kelas->id, 'nama' => $nama, 'bobot' => $komponen->bobot, 'jenis_evaluasi' => $komponen->jenis_evaluasi, 'urutan' => $komponen->urutan]);
        }
        $kelas->dosen()->attach($this->k->dosen->id, ['peran' => 'pengampu']);
        $komponen = $kelas->komponenNilai()->get()->keyBy('nama');
        app(CapaianService::class)->petakan($kelas, new PemetaanCapaianData([
            $komponen['Tugas']->id => [$this->k->kunci('Sub-1.1') => 100],
            $komponen['UAS']->id => [$this->k->kunci('CPMK-3') => 100],
        ]));
        foreach (range(1, 8) as $i) {
            $this->k->peserta(['Tugas' => 60 + $i, 'UTS' => 70, 'UAS' => 80], null, $kelas);
        }
        app(PenilaianService::class)->finalisasi($kelas, $this->k->dosen);

        $banyak = $buka();
        Model::preventLazyLoading(false);

        expect($banyak)->toBe($sedikit)->and($sedikit)->toBeLessThanOrEqual(16);
    });
});

describe('portal mahasiswa', function () {
    it('menampilkan capaian CPL dan CPMK milik sendiri', function () {
        [$detail] = finalkanKelasObe($this->k, [['Tugas' => 80, 'UTS' => 60, 'UAS' => 90]]);
        $mahasiswa = $detail->krs->mahasiswa;

        $hasil = $this->rekap->mahasiswa($mahasiswa);

        expect($hasil['cpl']->pluck('nilai', 'cpl.kode')->all())->toBe(['CPL-A' => 73.2, 'CPL-B' => 84.0])
            ->and($hasil['cpl'][0]['mata_kuliah']->all())->toBe([$this->k->mk->nama])
            ->and($hasil['mata_kuliah'][0]['cpmk']->pluck('nilai')->all())->toBe([70.0, 78.0, 90.0]);

        $this->actingAs($mahasiswa, 'mahasiswa')->get(route('mahasiswa.capaian'))
            ->assertOk()->assertSee('CPMK-2')->assertSee('84,0');

        // Another student of the class sees their own, empty, page.
        $lain = Mahasiswa::factory()->create(['prodi_id' => $this->k->prodi->id]);
        $this->actingAs($lain, 'mahasiswa')->get(route('mahasiswa.capaian'))
            ->assertOk()->assertDontSee('CPMK-2');
    });

    it('menolak portal lain membuka halaman capaian mahasiswa', function () {
        $this->actingAs($this->k->dosen, 'dosen')->get(route('mahasiswa.capaian'))->assertRedirect();
        expect($this->k->dosen->can('viewOwn', CapaianCplMahasiswa::class))->toBeFalse();
    });
});

describe('RPS satu sumber', function () {
    it('memasang versi CPMK berlaku dan menurunkan CPL darinya', function () {
        $rps = app(RpsService::class)->mulai($this->k->mk, $this->k->term, $this->k->dosen);

        expect($rps->cpmk_versi_id)->toBe($this->k->versi->id)
            ->and($rps->cpl->pluck('kode')->sort()->values()->all())->toBe(['CPL-A', 'CPL-B'])
            ->and($rps->cpl->firstWhere('kode', 'CPL-A')->pivot->rumusan)->toContain('CPMK-1 Menjelaskan konsep.');

        expect(fn () => app(RpsService::class)->simpanCpl($rps, [['prodi_cpl_id' => $this->k->cplA->id]]))
            ->toThrow(AturanAkademikException::class, 'mengikuti CPMK');

        $this->actingAs($this->k->dosen, 'dosen')->get(route('dosen.rps.susun', $this->k->mk))
            ->assertOk()->assertSee('Sub-2.1')->assertSee('Lihat / revisi CPMK');
    });

    it('menautkan pertemuan ke sub-CPMK versi yang sama saja', function () {
        $service = app(RpsService::class);
        $rps = $service->mulai($this->k->mk, $this->k->term, $this->k->dosen);
        $sub = (int) substr($this->k->kunci('Sub-2.1'), 2);

        $service->simpanPertemuan($rps, [['pertemuan_ke' => 1, 'kemampuan_akhir' => 'x', 'sub_cpmk_id' => $sub]]);

        expect($rps->pertemuan()->value('sub_cpmk_id'))->toBe($sub);

        // A sub-CPMK of the next draft version is not this plan's.
        $draf = app(CpmkService::class)->draf($this->k->kurikulum, $this->k->mk);
        $asing = $draf->cpmk[0]->sub[0]->id;

        expect(fn () => $service->simpanPertemuan($rps, [['pertemuan_ke' => 1, 'kemampuan_akhir' => 'x', 'sub_cpmk_id' => $asing]]))
            ->toThrow(AturanAkademikException::class, 'bukan milik versi');
    });

    it('memindahkan draf RPS ke versi CPMK yang baru diterbitkan', function () {
        $service = app(RpsService::class);
        $rps = $service->mulai($this->k->mk, $this->k->term, $this->k->dosen);
        $service->simpanPertemuan($rps, [['pertemuan_ke' => 1, 'kemampuan_akhir' => 'x', 'sub_cpmk_id' => (int) substr($this->k->kunci('Sub-1.1'), 2)]]);

        $cpmk = app(CpmkService::class);
        $baru = $cpmk->terbitkan($cpmk->draf($this->k->kurikulum, $this->k->mk), $this->k->staf);

        $lagi = $service->mulai($this->k->mk, $this->k->term, $this->k->dosen);

        expect($lagi->id)->toBe($rps->id)
            ->and($lagi->fresh()->cpmk_versi_id)->toBe($baru->id)
            // The week keeps its text; only the link to the archived row goes.
            ->and($lagi->pertemuan()->first()->sub_cpmk_id)->toBeNull()
            ->and($lagi->pertemuan()->first()->kemampuan_akhir)->toBe('x');
    });

    it('menghitung CPL terukur lewat CPMK pada analitik RPS', function () {
        $rps = app(RpsService::class)->mulai($this->k->mk, $this->k->term, $this->k->dosen);

        expect(app(RpsService::class)->cplTanpaPengukur($rps->load('cpl'), $this->k->kelas))->toBeEmpty();
    });
});

describe('LKPS', function () {
    it('mengisi tabel capaian pembelajaran dari angka tersimpan', function () {
        finalkanKelasObe($this->k, [
            ['Tugas' => 80, 'UTS' => 60, 'UAS' => 90],
            ['Tugas' => 40, 'UTS' => 50, 'UAS' => 60],
        ]);

        $tabel = collect(app(PerakitBorang::class)->rakit($this->k->prodi, $this->k->term))
            ->firstWhere('kunci', 'capaian_pembelajaran');

        expect($tabel['terisi'])->toBeTrue()
            ->and($tabel['baris'][0][0])->toBe('CPL-A')
            ->and($tabel['baris'][0][2])->toBe(2)
            ->and($tabel['baris'][0][4])->toBe(1);
    });

    it('menyebut alasannya ketika belum ada capaian tersimpan', function () {
        $tabel = collect(app(PerakitBorang::class)->rakit($this->k->prodi, $this->k->term))
            ->firstWhere('kunci', 'capaian_pembelajaran');

        expect($tabel['terisi'])->toBeFalse()
            ->and($tabel['alasan'])->toContain('belum dipetakan');
    });
});

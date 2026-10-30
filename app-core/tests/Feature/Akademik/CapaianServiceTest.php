<?php

declare(strict_types=1);

use App\DTOs\Akademik\PemetaanCapaianData;
use App\Exceptions\AturanAkademikException;
use App\Exceptions\PenilaianException;
use App\Models\Akademik\CapaianCplMahasiswa;
use App\Models\Akademik\CapaianCpmkMahasiswa;
use App\Models\Akademik\KomponenNilaiCpmk;
use App\Models\Akademik\Nilai;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\Staff;
use App\Models\System\LogAktivitas;
use App\Services\Akademik\CapaianService;
use App\Services\Akademik\CpmkService;
use App\Services\Akademik\PenilaianService;
use Database\Seeders\RolePermissionSeeder;
use Tests\Support\KampusObe;

/**
 * Grading by outcome: mapping, coverage, and the stored attainment
 * (ADR-038 §3–§4). The numbers are worked by hand in each test, because a
 * test that recomputes them the way the service does proves nothing.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->k = KampusObe::bangun();
    $this->capaian = app(CapaianService::class);
    $this->penilaian = app(PenilaianService::class);
});

/** @return array{cpmk: array<string, float>, cpl: array<string, float>} */
function capaianTersimpanObe(KampusObe $k, int $krsDetailId): array
{
    return [
        'cpmk' => CapaianCpmkMahasiswa::with('cpmk')->where('krs_detail_id', $krsDetailId)->get()
            ->mapWithKeys(fn ($b): array => [$b->cpmk->kode => $b->nilai])->sortKeys()->all(),
        'cpl' => CapaianCplMahasiswa::with('cpl')->where('krs_detail_id', $krsDetailId)->get()
            ->mapWithKeys(fn ($b): array => [$b->cpl->kode => $b->nilai])->sortKeys()->all(),
    ];
}

describe('perhitungan', function () {
    it('membobot komponen, sub-CPMK, dan CPMK ke CPL', function () {
        $this->capaian->petakan($this->k->kelas, new PemetaanCapaianData($this->k->pemetaanBaku()));
        $detail = $this->k->peserta(['Tugas' => 80, 'UTS' => 60, 'UAS' => 90]);

        $this->penilaian->finalisasi($this->k->kelas, $this->k->dosen);

        /*
         * Sub 1.1 = 80, Sub 1.2 = 60, Sub 2.1 = 60, Sub 2.2 = 90, CPMK-3 = 90
         * CPMK-1 = (50·80 + 50·60) / 100 = 70
         * CPMK-2 = (40·60 + 60·90) / 100 = 78
         * CPL-A  = (60·70 + 40·78) / 100 = 73,2
         * CPL-B  = (50·78 + 50·90) / 100 = 84
         */
        expect(capaianTersimpanObe($this->k, $detail->id))->toBe([
            'cpmk' => ['CPMK-1' => 70.0, 'CPMK-2' => 78.0, 'CPMK-3' => 90.0],
            'cpl' => ['CPL-A' => 73.2, 'CPL-B' => 84.0],
        ]);

        expect(CapaianCplMahasiswa::where('krs_detail_id', $detail->id)->first()->sks)->toBe(3);
    });

    it('menggabungkan dua komponen yang mengukur sub-CPMK yang sama, dan mencatat cakupan', function () {
        $this->capaian->petakan($this->k->kelas, new PemetaanCapaianData([
            $this->k->komponen['Tugas']->id => [$this->k->kunci('Sub-1.1') => 100],
            $this->k->komponen['UTS']->id => [$this->k->kunci('Sub-1.2') => 100],
            $this->k->komponen['UAS']->id => [$this->k->kunci('Sub-1.1') => 50, $this->k->kunci('Sub-2.2') => 50],
        ]));
        $detail = $this->k->peserta(['Tugas' => 80, 'UTS' => 60, 'UAS' => 90]);

        $this->penilaian->finalisasi($this->k->kelas, $this->k->dosen);

        /*
         * Sub 1.1 = (30·100·80 + 40·50·90) / (3000 + 2000) = 84
         * CPMK-1  = (50·84 + 50·60) / 100 = 72
         * CPMK-2  = Sub 2.2 alone = 90, over 60% of its weight — Sub 2.1 was
         *           never assessed, and is not counted as zero against anyone
         * CPMK-3  = not measured, no row
         * CPL-A   = (60·72 + 40·90) / 100 = 79,2
         * CPL-B   = CPMK-2 alone = 90
         */
        expect(capaianTersimpanObe($this->k, $detail->id))->toBe([
            'cpmk' => ['CPMK-1' => 72.0, 'CPMK-2' => 90.0],
            'cpl' => ['CPL-A' => 79.2, 'CPL-B' => 90.0],
        ]);

        expect(CapaianCpmkMahasiswa::where('krs_detail_id', $detail->id)->where('cpmk_id', $this->k->cpmkId('CPMK-2'))->value('cakupan'))
            ->toBe(60);
    });

    it('tidak menulis apa pun untuk kelas tanpa pemetaan', function () {
        $this->k->peserta(['Tugas' => 80, 'UTS' => 60, 'UAS' => 90]);

        $this->penilaian->finalisasi($this->k->kelas, $this->k->dosen);

        expect(CapaianCpmkMahasiswa::count())->toBe(0)
            ->and($this->capaian->hitungKelas($this->k->kelas))->toBe(0);
    });

    it('mencatat satu jejak audit untuk perhitungan pertama', function () {
        $this->capaian->petakan($this->k->kelas, new PemetaanCapaianData($this->k->pemetaanBaku()));
        $this->k->peserta(['Tugas' => 80, 'UTS' => 60, 'UAS' => 90]);
        $this->k->peserta(['Tugas' => 70, 'UTS' => 70, 'UAS' => 70]);

        $this->penilaian->finalisasi($this->k->kelas, $this->k->dosen);

        $log = LogAktivitas::where('event', 'capaian_dihitung')->sole();

        expect($log->description)->toContain('2 mahasiswa')->toContain('6 baris CPMK')->toContain('4 baris CPL');
    });
});

describe('koreksi nilai', function () {
    beforeEach(function () {
        $this->capaian->petakan($this->k->kelas, new PemetaanCapaianData($this->k->pemetaanBaku()));
        $this->detail = $this->k->peserta(['Tugas' => 80, 'UTS' => 60, 'UAS' => 90]);
        $this->penilaian->finalisasi($this->k->kelas, $this->k->dosen);
        $this->nilai = Nilai::where('krs_detail_id', $this->detail->id)->sole();
    });

    it('menghitung ulang capaian ketika komponennya dikoreksi, ter-audit per angka', function () {
        $hasil = $this->penilaian->koreksiKomponen(
            $this->nilai,
            [$this->k->komponen['UTS']->id => 80.0],
            'Salah input UTS dari lembar jawaban.',
            $this->k->staf,
        );

        /*
         * UTS 60 → 80: Sub 1.2 = Sub 2.1 = 80
         * CPMK-1 = 80, CPMK-2 = (40·80 + 60·90) / 100 = 86
         * CPL-A = (60·80 + 40·86) / 100 = 82,4 · CPL-B = (50·86 + 50·90) / 100 = 88
         * Akhir = 80·0,3 + 80·0,3 + 90·0,4 = 84
         */
        expect((float) $hasil->nilai_angka)->toBe(84.0)
            ->and(capaianTersimpanObe($this->k, $this->detail->id))->toBe([
                'cpmk' => ['CPMK-1' => 80.0, 'CPMK-2' => 86.0, 'CPMK-3' => 90.0],
                'cpl' => ['CPL-A' => 82.4, 'CPL-B' => 88.0],
            ]);

        $baris = CapaianCpmkMahasiswa::where('krs_detail_id', $this->detail->id)->where('cpmk_id', $this->k->cpmkId('CPMK-1'))->sole();
        $jejak = LogAktivitas::where('subject_type', $baris->getMorphClass())->where('subject_id', $baris->id)->where('event', 'updated')->sole();

        expect($jejak->changes['nilai'])->toEqual(['old' => 70.0, 'new' => 80.0]);

        expect(LogAktivitas::where('event', 'corrected')->where('subject_id', $this->nilai->id)->sole()->description)
            ->toContain('UTS 60,00 → 80,00')
            ->toContain('Salah input UTS');
    });

    it('membiarkan capaian ketika hanya nilai akhir yang dikoreksi', function () {
        $sebelum = capaianTersimpanObe($this->k, $this->detail->id);

        $this->penilaian->koreksi($this->nilai, 95.0, 'Kesalahan pembulatan pada rekap.', $this->k->staf);

        expect((float) $this->nilai->fresh()->nilai_angka)->toBe(95.0)
            ->and(capaianTersimpanObe($this->k, $this->detail->id))->toBe($sebelum);
    });

    it('mengoreksi lewat layar staf dengan komponen', function () {
        // Correction is nilai.manage, which BAAK does not hold.
        $admin = Staff::factory()->create();
        $admin->assignRole('super-admin');

        $this->actingAs($this->k->staf, 'staff')
            ->post(route('admin.koreksi-nilai.simpan', $this->nilai), [
                'komponen' => [$this->k->komponen['UAS']->id => 50],
                'alasan' => 'Lembar UAS tertukar dengan mahasiswa lain.',
            ])
            ->assertForbidden();

        $this->actingAs($admin, 'staff')
            ->post(route('admin.koreksi-nilai.simpan', $this->nilai), [
                'komponen' => [$this->k->komponen['UAS']->id => 50],
                'alasan' => 'Lembar UAS tertukar dengan mahasiswa lain.',
            ])
            ->assertSessionHas('sukses');

        // UAS 90 → 50: Sub 2.2 = CPMK-3 = 50 → CPMK-2 = (24 + 30) = 54
        expect(capaianTersimpanObe($this->k, $this->detail->id)['cpmk']['CPMK-2'])->toBe(54.0);
    });

    it('menolak komponen yang bukan milik kelas', function () {
        expect(fn () => $this->penilaian->koreksiKomponen($this->nilai, [999999 => 70.0], 'Alasan yang cukup.', $this->k->staf))
            ->toThrow(AturanAkademikException::class);
    });
});

describe('pemetaan dan cakupan', function () {
    it('menolak baris yang porsinya tidak 100', function () {
        expect(fn () => $this->capaian->petakan($this->k->kelas, new PemetaanCapaianData([
            $this->k->komponen['UTS']->id => [$this->k->kunci('Sub-1.2') => 50, $this->k->kunci('Sub-2.1') => 40],
        ])))->toThrow(AturanAkademikException::class, 'UTS berjumlah 90%');
    });

    it('menolak sasaran di luar versi kelas, termasuk CPMK yang punya sub', function () {
        // CPMK-1 has parts: mapping to the parent would count its marks twice.
        expect(fn () => $this->capaian->petakan($this->k->kelas, new PemetaanCapaianData([
            $this->k->komponen['UTS']->id => ['c:'.$this->k->cpmkId('CPMK-1') => 100],
        ])))->toThrow(AturanAkademikException::class, 'bukan milik versi');
    });

    it('menolak memetakan ulang kelas yang sudah final', function () {
        $this->k->kelas->update(['status_nilai' => 'final']);

        expect(fn () => $this->capaian->petakan($this->k->kelas, new PemetaanCapaianData($this->k->pemetaanBaku())))
            ->toThrow(PenilaianException::class);
    });

    it('mengganti pemetaan lama seluruhnya', function () {
        $this->capaian->petakan($this->k->kelas, new PemetaanCapaianData($this->k->pemetaanBaku()));
        $this->capaian->petakan($this->k->kelas, new PemetaanCapaianData([
            $this->k->komponen['UAS']->id => [$this->k->kunci('CPMK-3') => 100],
        ]));

        expect(KomponenNilaiCpmk::count())->toBe(1);
    });

    it('memperingatkan sub-CPMK yang tidak diukur komponen mana pun', function () {
        $this->capaian->petakan($this->k->kelas, new PemetaanCapaianData([
            $this->k->komponen['Tugas']->id => [$this->k->kunci('Sub-1.1') => 100],
            $this->k->komponen['UTS']->id => [$this->k->kunci('Sub-1.2') => 100],
        ]));

        $cakupan = $this->capaian->cakupan($this->k->kelas);

        expect($cakupan['tak_terukur']->pluck('kode')->values()->all())->toBe(['Sub-2.1', 'Sub-2.2', 'CPMK-3'])
            ->and($cakupan['komponen_tanpa']->pluck('nama')->all())->toBe(['UAS']);

        $this->actingAs($this->k->dosen, 'dosen')
            ->get(route('dosen.nilai.kelas', $this->k->kelas))
            ->assertOk()
            ->assertSee('3 sub-CPMK/CPMK belum diukur komponen mana pun: Sub-2.1, Sub-2.2, CPMK-3', false);
    });

    it('menandai pemetaan versi lama setelah revisi diterbitkan', function () {
        $this->capaian->petakan($this->k->kelas, new PemetaanCapaianData($this->k->pemetaanBaku()));

        $cpmk = app(CpmkService::class);
        $cpmk->terbitkan($cpmk->draf($this->k->kurikulum, $this->k->mk), $this->k->staf);

        $cakupan = $this->capaian->cakupan($this->k->kelas->fresh());

        expect($cakupan['versi_lama']->id)->toBe($this->k->versi->id)
            ->and($cakupan['versi']->versi)->toBe(2)
            ->and($cakupan['tak_terukur'])->toHaveCount(5);
    });

    it('menyimpan pemetaan dari layar rencana evaluasi', function () {
        $this->actingAs($this->k->dosen, 'dosen')
            ->post(route('dosen.nilai.pemetaan', $this->k->kelas), ['porsi' => $this->k->pemetaanBaku()])
            ->assertSessionHas('sukses');

        expect(KomponenNilaiCpmk::count())->toBe(5);

        $lain = Dosen::factory()->create();
        $lain->assignRole('dosen');

        $this->actingAs($lain, 'dosen')
            ->post(route('dosen.nilai.pemetaan', $this->k->kelas), ['porsi' => $this->k->pemetaanBaku()])
            ->assertForbidden();
    });
});

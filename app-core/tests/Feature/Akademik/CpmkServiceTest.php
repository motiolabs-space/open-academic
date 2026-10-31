<?php

declare(strict_types=1);

use App\DTOs\Akademik\CpmkData;
use App\DTOs\Akademik\PemetaanCapaianData;
use App\DTOs\Akademik\RumusanCpmkData;
use App\Enums\StatusCpmk;
use App\Exceptions\AturanAkademikException;
use App\Models\Akademik\CpmkVersi;
use App\Models\Akademik\Kurikulum;
use App\Models\Akademik\MataKuliah;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\ProdiCpl;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\Staff;
use App\Services\Akademik\CapaianService;
use App\Services\Akademik\CpmkService;
use Database\Seeders\RolePermissionSeeder;
use Tests\Support\KampusObe;

/**
 * The outcome hierarchy and its versions (ADR-038 §1–§2).
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->k = KampusObe::bangun();
    $this->cpmk = app(CpmkService::class);
});

describe('hierarki dan versi', function () {
    it('menerbitkan versi pertama lengkap dengan sub-CPMK dan bobot CPL', function () {
        $v = $this->k->versi;

        expect($v->status)->toBe(StatusCpmk::Berlaku)
            ->and($v->versi)->toBe(1)
            ->and($v->kunci_aktif)->toBe($this->k->kurikulum->id.':'.$this->k->mk->id)
            ->and($v->cpmk->pluck('kode')->all())->toBe(['CPMK-1', 'CPMK-2', 'CPMK-3'])
            ->and($v->cpmk[0]->sub->pluck('bobot')->all())->toBe([50, 50])
            ->and($v->cpmk[1]->cpl->pluck('pivot.bobot', 'kode')->all())->toBe(['CPL-A' => 40, 'CPL-B' => 50]);
    });

    it('menolak menyunting versi yang sudah berlaku', function () {
        expect(fn () => $this->cpmk->simpan($this->k->versi, KampusObe::rumusanBaku($this->k->cplA, $this->k->cplB)))
            ->toThrow(AturanAkademikException::class, 'tidak dapat disunting');
    });

    it('membuat revisi sebagai versi baru yang menyalin versi berlaku', function () {
        $draf = $this->cpmk->draf($this->k->kurikulum, $this->k->mk, $this->k->staf);

        expect($draf->versi)->toBe(2)
            ->and($draf->status)->toBe(StatusCpmk::Draft)
            ->and($draf->cpmk->pluck('kode')->all())->toBe(['CPMK-1', 'CPMK-2', 'CPMK-3'])
            // The copy is new rows, never the rows grades are mapped to.
            ->and($draf->cpmk->pluck('id')->intersect($this->k->versi->cpmk->pluck('id')))->toBeEmpty();

        // Asking again returns the draft in progress, not a third version.
        expect($this->cpmk->draf($this->k->kurikulum, $this->k->mk)->id)->toBe($draf->id);
    });

    it('mengarsipkan versi lama saat revisi diterbitkan, tanpa mengubah barisnya', function () {
        $lamaCpmk = $this->k->versi->cpmk->pluck('rumusan', 'id')->all();

        $draf = $this->cpmk->draf($this->k->kurikulum, $this->k->mk, $this->k->staf);
        $data = KampusObe::rumusanBaku($this->k->cplA, $this->k->cplB);
        $this->cpmk->simpan($draf, new RumusanCpmkData([
            new CpmkData('CPMK-1', 'Rumusan baru.', $data->cpmk[0]->cpl, $data->cpmk[0]->sub),
            $data->cpmk[1],
            $data->cpmk[2],
        ]));

        $baru = $this->cpmk->terbitkan($draf->fresh(), $this->k->dosen);

        expect($baru->status)->toBe(StatusCpmk::Berlaku)
            ->and($baru->diterbitkan_oleh)->toBe($this->k->dosen->nama)
            ->and($this->k->versi->fresh()->status)->toBe(StatusCpmk::Diarsipkan)
            ->and($this->k->versi->fresh()->kunci_aktif)->toBeNull()
            ->and($this->k->versi->fresh()->cpmk->pluck('rumusan', 'id')->all())->toBe($lamaCpmk)
            ->and($this->cpmk->berlakuUntuk($this->k->mk)->id)->toBe($baru->id);
    });

    it('menyebut setiap bobot yang tidak menutup sebelum terbit', function () {
        $draf = $this->cpmk->draf($this->k->kurikulum, $this->k->mk);

        $this->cpmk->simpan($draf, RumusanCpmkData::fromArray([
            ['kode' => 'CPMK-1', 'rumusan' => 'A', 'cpl' => [['prodi_cpl_id' => $this->k->cplA->id, 'bobot' => 70]],
                'sub' => [['kode' => 'S1', 'rumusan' => 'x', 'bobot' => 30], ['kode' => 'S2', 'rumusan' => 'y', 'bobot' => 30]]],
            ['kode' => 'CPMK-2', 'rumusan' => 'B', 'cpl' => [], 'sub' => []],
        ]));

        $kurang = $this->cpmk->kekurangan($draf->fresh());

        expect($kurang)->toHaveCount(3)
            ->and(implode(' ', $kurang))->toContain('CPMK-2 belum dikaitkan')
            ->toContain('sub-CPMK pada CPMK-1 berjumlah 60%')
            ->toContain('CPL-A berjumlah 70%');

        expect(fn () => $this->cpmk->terbitkan($draf->fresh(), $this->k->staf))
            ->toThrow(AturanAkademikException::class, 'belum dapat diterbitkan');
    });

    it('menolak CPL milik program studi lain dan kode ganda', function () {
        $lain = ProdiCpl::create(['prodi_id' => Prodi::factory()->create()->id, 'kode' => 'X', 'kategori' => 'sikap', 'deskripsi' => 'x']);
        $draf = $this->cpmk->draf($this->k->kurikulum, $this->k->mk);

        expect(fn () => $this->cpmk->simpan($draf, RumusanCpmkData::fromArray([
            ['kode' => 'C1', 'rumusan' => 'A', 'cpl' => [['prodi_cpl_id' => $lain->id, 'bobot' => 100]]],
        ])))->toThrow(AturanAkademikException::class, 'bukan milik program studi');

        expect(fn () => $this->cpmk->simpan($draf, RumusanCpmkData::fromArray([
            ['kode' => 'C1', 'rumusan' => 'A'],
            ['kode' => 'c1', 'rumusan' => 'B'],
        ])))->toThrow(AturanAkademikException::class, 'lebih dari sekali');
    });

    it('menolak mata kuliah yang tidak ada di kurikulum', function () {
        $asing = MataKuliah::factory()->create(['prodi_id' => $this->k->prodi->id]);

        expect(fn () => $this->cpmk->draf($this->k->kurikulum, $asing))
            ->toThrow(AturanAkademikException::class, 'tidak termasuk');
    });

    it('memilih kurikulum aktif prodi bila mata kuliah ada di dua versi', function () {
        $baru = Kurikulum::factory()->create(['prodi_id' => $this->k->prodi->id, 'kode' => 'KUR2026', 'tahun_mulai' => 2026, 'is_active' => false]);
        $baru->mataKuliah()->attach($this->k->mk->id, ['semester' => 2, 'jenis' => 'wajib']);

        expect($this->cpmk->kurikulumUntuk($this->k->mk)->id)->toBe($this->k->kurikulum->id);

        // Same course, other curriculum: its own set, versioned apart.
        expect($this->cpmk->berlakuUntuk($this->k->mk, $baru))->toBeNull()
            ->and($this->cpmk->draf($baru, $this->k->mk)->versi)->toBe(1);
    });
});

describe('sasaran ukur dan ringkasan', function () {
    it('menjadikan sub-CPMK sasaran, atau CPMK bila tanpa sub', function () {
        expect($this->cpmk->sasaran($this->k->versi)->pluck('kode')->values()->all())
            ->toBe(['Sub-1.1', 'Sub-1.2', 'Sub-2.1', 'Sub-2.2', 'CPMK-3']);
    });

    it('meringkas mata kuliah kurikulum beserta status versinya', function () {
        $lain = MataKuliah::factory()->create(['prodi_id' => $this->k->prodi->id, 'kode' => 'OBE102']);
        $this->k->kurikulum->mataKuliah()->attach($lain->id, ['semester' => 2, 'jenis' => 'wajib']);
        $this->cpmk->draf($this->k->kurikulum, $this->k->mk);

        $ringkas = $this->cpmk->ringkasKurikulum($this->k->kurikulum)->keyBy(fn ($b) => $b['mata_kuliah']->kode);

        expect($ringkas['OBE101']['berlaku']->versi)->toBe(1)
            ->and($ringkas['OBE101']['berlaku']->cpmk_count)->toBe(3)
            ->and($ringkas['OBE101']['draf']->versi)->toBe(2)
            ->and($ringkas['OBE102']['berlaku'])->toBeNull();

        expect($this->cpmk->riwayat($this->k->kurikulum, $this->k->mk)->pluck('versi')->all())->toBe([2, 1]);
    });

    it('membiarkan kelas final tetap pada versi yang dipakai menilainya', function () {
        app(CapaianService::class)->petakan($this->k->kelas, new PemetaanCapaianData($this->k->pemetaanBaku()));
        $this->k->kelas->update(['status_nilai' => 'final']);

        $draf = $this->cpmk->draf($this->k->kurikulum, $this->k->mk);
        $this->cpmk->terbitkan($draf, $this->k->staf);

        expect($this->cpmk->versiUntukKelas($this->k->kelas->fresh())->id)->toBe($this->k->versi->id)
            ->and($this->cpmk->berlakuUntuk($this->k->mk)->id)->toBe($draf->id);
    });
});

describe('policy', function () {
    it('membuka penyusunan bagi prodi dan dosen pengampu saja', function () {
        $dosenLain = Dosen::factory()->create(['prodi_id' => $this->k->prodi->id]);
        $dosenLain->assignRole('dosen');

        $keuangan = Staff::factory()->create();
        $keuangan->assignRole('keuangan');

        $draf = $this->cpmk->draf($this->k->kurikulum, $this->k->mk);

        expect($this->k->staf->can('create', [CpmkVersi::class, $this->k->mk]))->toBeTrue()
            ->and($this->k->dosen->can('create', [CpmkVersi::class, $this->k->mk]))->toBeTrue()
            ->and($dosenLain->can('create', [CpmkVersi::class, $this->k->mk]))->toBeFalse()
            ->and($keuangan->can('create', [CpmkVersi::class, $this->k->mk]))->toBeFalse()
            ->and($this->k->dosen->can('update', $draf))->toBeTrue()
            // A version in force is closed to everyone, the programme included.
            ->and($this->k->staf->can('update', $this->k->versi))->toBeFalse();
    });
});

describe('layar', function () {
    it('menyimpan dan menerbitkan lewat layar prodi', function () {
        $draf = $this->cpmk->draf($this->k->kurikulum, $this->k->mk);

        $this->actingAs($this->k->staf, 'staff')
            ->get(route('admin.cpmk.edit', [$this->k->kurikulum, $this->k->mk]))
            ->assertOk()
            ->assertSee('CPMK-2');

        $this->actingAs($this->k->staf, 'staff')
            ->put(route('admin.cpmk.simpan', $draf), ['cpmk' => [
                ['kode' => 'CPMK-1', 'rumusan' => 'Satu-satunya.', 'cpl' => [
                    ['prodi_cpl_id' => $this->k->cplA->id, 'bobot' => 100],
                    ['prodi_cpl_id' => $this->k->cplB->id, 'bobot' => 100],
                ]],
            ]])
            ->assertSessionHas('sukses');

        $this->actingAs($this->k->staf, 'staff')
            ->post(route('admin.cpmk.terbitkan', $draf))
            ->assertSessionHas('sukses');

        expect($this->cpmk->berlakuUntuk($this->k->mk)->cpmk->pluck('kode')->all())->toBe(['CPMK-1']);
    });

    it('membuka penyunting CPMK dari RPS hanya untuk dosen pengampu', function () {
        $this->actingAs($this->k->dosen, 'dosen')->get(route('dosen.cpmk', $this->k->mk))->assertOk();

        $lain = Dosen::factory()->create();
        $lain->assignRole('dosen');

        $this->actingAs($lain, 'dosen')->get(route('dosen.cpmk', $this->k->mk))->assertForbidden();
    });

    it('menampilkan daftar mata kuliah kurikulum pada layar prodi', function () {
        $this->actingAs($this->k->staf, 'staff')
            ->get(route('admin.cpmk', ['prodi' => $this->k->prodi->uuid]))
            ->assertOk()
            ->assertSee('OBE101');
    });
});

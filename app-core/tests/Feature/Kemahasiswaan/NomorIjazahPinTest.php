<?php

declare(strict_types=1);

use App\Exceptions\AturanAkademikException;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Kemahasiswaan\WisudaPeriode;
use App\Models\Kemahasiswaan\WisudaPeserta;
use App\Models\Kemahasiswaan\Yudisium;
use App\Models\Sdm\Staff;
use App\Services\Kemahasiswaan\WisudaService;
use Database\Seeders\RolePermissionSeeder;

/**
 * Diploma numbers come from the national service (PIN) by default. They are
 * recorded, never generated — a generated number prints cleanly and fails
 * verification years later, in the graduate's hands.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->term = TahunAkademik::factory()->berjalan()->create(['is_active' => true]);
    $this->prodi = Prodi::factory()->create(['kode' => 'IF', 'kode_pddikti' => '55201']);
    $this->staf = Staff::factory()->create();
    $this->staf->assignRole('baak');

    $this->periode = WisudaPeriode::create([
        'nama' => 'Wisuda Periode I 2026',
        'tanggal' => '2026-11-15',
        'lokasi' => 'Auditorium',
        'is_pendaftaran_dibuka' => true,
    ]);

    $this->wisuda = app(WisudaService::class);
});

function pesertaWisuda(string $nim): WisudaPeserta
{
    $mahasiswa = Mahasiswa::factory()->create(['prodi_id' => test()->prodi->id, 'nim' => $nim]);

    $yudisium = Yudisium::create([
        'mahasiswa_id' => $mahasiswa->id,
        'tahun_akademik_id' => test()->term->id,
        'total_sks' => 144,
        'ipk' => 3.50,
        'status' => 'ditetapkan',
        'tanggal_lulus' => now(),
    ]);

    return test()->wisuda->daftarkan(test()->periode->fresh(), $yudisium);
}

describe('bawaan: PIN', function () {
    it('menolak membangkitkan nomor ijazah, dan mengatakan jalurnya', function () {
        pesertaWisuda('2201001');

        expect(fn () => $this->wisuda->terbitkanNomorIjazah($this->periode, $this->staf, '{tahun}/{prodi}/{urut}'))
            ->toThrow(AturanAkademikException::class, 'Penomoran Ijazah Nasional');

        expect(WisudaPeserta::first()->nomor_ijazah)->toBeNull();
    });

    it('mencatat nomor hasil reservasi, satu lulusan per baris, dengan pemisah apa pun', function () {
        pesertaWisuda('2201001');
        pesertaWisuda('2201002');
        pesertaWisuda('2201003');

        $hasil = $this->wisuda->catatNomorIjazah($this->periode, $this->staf, implode("\n", [
            '# NIM, nomor',
            '2201001, 1234567890000001',
            '2201002;1234567890000002',
            "2201003\t1234567890000003",
            '',
        ]));

        expect($hasil)->toBe(['dicatat' => 3, 'dilewati' => 0, 'ditolak' => []])
            ->and(WisudaPeserta::query()->orderBy('nomor_urut')->pluck('nomor_ijazah')->all())
            ->toBe(['1234567890000001', '1234567890000002', '1234567890000003']);
    });

    it('menilai tiap baris sendiri: yang baik dicatat, yang buruk dilaporkan dengan sebabnya', function () {
        pesertaWisuda('2201001');
        pesertaWisuda('2201002');

        $hasil = $this->wisuda->catatNomorIjazah($this->periode, $this->staf, implode("\n", [
            '2201001, 1234567890000001',
            '9999999, 1234567890000009',
            '2201002',
        ]));

        expect($hasil['dicatat'])->toBe(1)
            ->and($hasil['ditolak'])->toHaveCount(2)
            ->and($hasil['ditolak'][0])->toContain('9999999')->toContain('tidak terdaftar')
            ->and($hasil['ditolak'][1])->toContain('2201002')->toContain('butuh NIM dan nomor');
    });

    it('tidak pernah menimpa nomor yang sudah tercatat, dan mengabaikan impor ulang yang sama', function () {
        pesertaWisuda('2201001');
        $this->wisuda->catatNomorIjazah($this->periode, $this->staf, '2201001, 1234567890000001');

        $sama = $this->wisuda->catatNomorIjazah($this->periode, $this->staf, '2201001, 1234567890000001');
        $beda = $this->wisuda->catatNomorIjazah($this->periode, $this->staf, '2201001, 9999999999999999');

        expect($sama['dilewati'])->toBe(1)
            ->and($beda['dicatat'])->toBe(0)
            ->and($beda['ditolak'][0])->toContain('tidak ditimpa')
            ->and(WisudaPeserta::first()->nomor_ijazah)->toBe('1234567890000001');
    });

    it('menolak satu nomor untuk dua lulusan, di dalam berkas maupun terhadap yang sudah tersimpan', function () {
        pesertaWisuda('2201001');
        pesertaWisuda('2201002');
        pesertaWisuda('2201003');
        $this->wisuda->catatNomorIjazah($this->periode, $this->staf, '2201001, 1234567890000001');

        $hasil = $this->wisuda->catatNomorIjazah($this->periode, $this->staf, implode("\n", [
            '2201002, 1234567890000001',
            '2201003, 1234567890000003',
            '2201002, 1234567890000003',
        ]));

        expect($hasil['dicatat'])->toBe(1)
            ->and($hasil['ditolak'])->toHaveCount(2)
            ->and($hasil['ditolak'][0])->toContain('sudah dipakai lulusan lain')
            ->and(WisudaPeserta::whereNotNull('nomor_ijazah')->count())->toBe(2);
    });

    it('mengunci peserta yang nomornya sudah dicatat, sama seperti nomor yang dibangkitkan', function () {
        $peserta = pesertaWisuda('2201001');
        $this->wisuda->catatNomorIjazah($this->periode, $this->staf, '2201001, 1234567890000001');

        expect(fn () => $this->wisuda->batalkan($peserta->fresh()))
            ->toThrow(AturanAkademikException::class, 'nomor ijazah');
    });

    it('menampilkan formulir pencatatan PIN, bukan formulir pembangkit', function () {
        pesertaWisuda('2201001');

        $this->actingAs($this->staf, 'staff')
            ->get('/admin/wisuda')
            ->assertOk()
            ->assertSee('Catat Nomor Ijazah dari PIN')
            ->assertSee('belum dicatat dari PIN')
            ->assertDontSee('Terbitkan Nomor Ijazah');
    });

    it('mencatat lewat layar dan melaporkan baris yang ditolak', function () {
        pesertaWisuda('2201001');

        $this->actingAs($this->staf, 'staff')
            ->post("/admin/wisuda/periode/{$this->periode->uuid}/ijazah/catat", [
                'isian' => "2201001, 1234567890000001\n7777777, 1234567890000002",
            ])
            ->assertRedirect()
            ->assertSessionHas('peringatan', fn (string $pesan): bool => str_contains($pesan, '1 nomor ijazah dicatat') && str_contains($pesan, '7777777'));

        expect(WisudaPeserta::first()->nomor_ijazah)->toBe('1234567890000001');
    });
});

describe('lokal, bila kampus memilihnya', function () {
    it('menampilkan formulir pembangkit dan tetap menerima pencatatan manual', function () {
        config(['academic.ijazah.sumber' => 'lokal']);
        pesertaWisuda('2201001');

        $this->actingAs($this->staf, 'staff')
            ->get('/admin/wisuda')
            ->assertOk()
            ->assertSee('Terbitkan Nomor Ijazah')
            ->assertDontSee('Catat Nomor Ijazah dari PIN');

        $hasil = $this->wisuda->catatNomorIjazah($this->periode, $this->staf, '2201001, LOKAL/0001');

        expect($hasil['dicatat'])->toBe(1);
    });
});

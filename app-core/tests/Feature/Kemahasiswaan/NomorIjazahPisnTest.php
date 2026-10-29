<?php

declare(strict_types=1);

use App\Enums\EducationLevel;
use App\Enums\SumberNomorIjazah;
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
 * Diploma numbers come from the national service (PISN) by default. They are
 * recorded, never generated — a generated number prints cleanly and fails
 * verification years later, in the graduate's hands (ADR-013, ADR-036 §2).
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->term = TahunAkademik::factory()->berjalan()->create(['is_active' => true]);
    $this->prodi = Prodi::factory()->create(['kode' => '55201', 'kode_pddikti' => fake()->uuid()]);
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

describe('bawaan: PISN', function () {
    it('menolak membangkitkan nomor ijazah, dan mengatakan jalurnya', function () {
        pesertaWisuda('2201001');

        expect(fn () => $this->wisuda->terbitkanNomorIjazah($this->periode, $this->staf, '{tahun}/{prodi}/{urut}'))
            ->toThrow(AturanAkademikException::class, 'Penomoran Ijazah dan Sertifikat Profesi Nasional');

        expect(WisudaPeserta::first()->nomor_ijazah)->toBeNull();
    });

    it('mencatat nomor terbitan PISN, satu lulusan per baris, dengan pemisah apa pun', function () {
        pesertaWisuda('2201001');
        pesertaWisuda('2201002');
        pesertaWisuda('2201003');

        $hasil = $this->wisuda->catatNomorIjazah($this->periode, $this->staf, implode("\n", [
            '# NIM, nomor',
            '2201001, 001001552012026100001',
            '2201002;001001552012026100002',
            "2201003\t001001552012026100003",
            '',
        ]));

        expect($hasil)->toBe(['dicatat' => 3, 'dilewati' => 0, 'ditolak' => []])
            ->and(WisudaPeserta::query()->orderBy('nomor_urut')->pluck('nomor_ijazah')->all())
            ->toBe(['001001552012026100001', '001001552012026100002', '001001552012026100003']);
    });

    it('menilai tiap baris sendiri: yang baik dicatat, yang buruk dilaporkan dengan sebabnya', function () {
        pesertaWisuda('2201001');
        pesertaWisuda('2201002');

        $hasil = $this->wisuda->catatNomorIjazah($this->periode, $this->staf, implode("\n", [
            '2201001, 001001552012026100001',
            '9999999, 001001552012026100009',
            '2201002',
        ]));

        expect($hasil['dicatat'])->toBe(1)
            ->and($hasil['ditolak'])->toHaveCount(2)
            ->and($hasil['ditolak'][0])->toContain('9999999')->toContain('tidak terdaftar')
            ->and($hasil['ditolak'][1])->toContain('2201002')->toContain('butuh NIM dan nomor');
    });

    it('tidak pernah menimpa nomor yang sudah tercatat, dan mengabaikan impor ulang yang sama', function () {
        pesertaWisuda('2201001');
        $this->wisuda->catatNomorIjazah($this->periode, $this->staf, '2201001, 001001552012026100001');

        $sama = $this->wisuda->catatNomorIjazah($this->periode, $this->staf, '2201001, 001001552012026100001');
        $beda = $this->wisuda->catatNomorIjazah($this->periode, $this->staf, '2201001, 9999999999999999');

        expect($sama['dilewati'])->toBe(1)
            ->and($beda['dicatat'])->toBe(0)
            ->and($beda['ditolak'][0])->toContain('tidak ditimpa')
            ->and(WisudaPeserta::first()->nomor_ijazah)->toBe('001001552012026100001');
    });

    it('menolak satu nomor untuk dua lulusan, di dalam berkas maupun terhadap yang sudah tersimpan', function () {
        pesertaWisuda('2201001');
        pesertaWisuda('2201002');
        pesertaWisuda('2201003');
        $this->wisuda->catatNomorIjazah($this->periode, $this->staf, '2201001, 001001552012026100001');

        $hasil = $this->wisuda->catatNomorIjazah($this->periode, $this->staf, implode("\n", [
            '2201002, 001001552012026100001',
            '2201003, 001001552012026100003',
            '2201002, 001001552012026100003',
        ]));

        expect($hasil['dicatat'])->toBe(1)
            ->and($hasil['ditolak'])->toHaveCount(2)
            ->and($hasil['ditolak'][0])->toContain('sudah dipakai lulusan lain')
            ->and(WisudaPeserta::whereNotNull('nomor_ijazah')->count())->toBe(2);
    });

    it('mengunci peserta yang nomornya sudah dicatat, sama seperti nomor yang dibangkitkan', function () {
        $peserta = pesertaWisuda('2201001');
        $this->wisuda->catatNomorIjazah($this->periode, $this->staf, '2201001, 001001552012026100001');

        expect(fn () => $this->wisuda->batalkan($peserta->fresh()))
            ->toThrow(AturanAkademikException::class, 'nomor ijazah');
    });

    it('menampilkan formulir pencatatan PISN, bukan formulir pembangkit', function () {
        pesertaWisuda('2201001');

        $this->actingAs($this->staf, 'staff')
            ->get('/admin/wisuda')
            ->assertOk()
            ->assertSee('Catat Nomor Ijazah dari PISN')
            ->assertSee('belum dicatat dari PISN')
            ->assertDontSee('Terbitkan Nomor Ijazah');
    });

    it('mencatat lewat layar dan melaporkan baris yang ditolak', function () {
        pesertaWisuda('2201001');

        $this->actingAs($this->staf, 'staff')
            ->post("/admin/wisuda/periode/{$this->periode->uuid}/ijazah/catat", [
                'isian' => "2201001, 001001552012026100001\n7777777, 001001552012026100002",
            ])
            ->assertRedirect()
            ->assertSessionHas('peringatan', fn (string $pesan): bool => str_contains($pesan, '1 nomor ijazah dicatat') && str_contains($pesan, '7777777'));

        expect(WisudaPeserta::first()->nomor_ijazah)->toBe('001001552012026100001');
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
            ->assertDontSee('Catat Nomor Ijazah dari PISN');

        $hasil = $this->wisuda->catatNomorIjazah($this->periode, $this->staf, '2201001, LOKAL/0001');

        expect($hasil['dicatat'])->toBe(1);
    });
});

describe('format nomor PISN', function () {
    it('menolak nomor yang strukturnya bukan milik lulusan ini, dengan sebab per baris', function () {
        foreach (['2201001', '2201002', '2201003', '2201004', '2201005'] as $nim) {
            pesertaWisuda($nim);
        }

        $hasil = $this->wisuda->catatNomorIjazah($this->periode, $this->staf, implode("\n", [
            '2201001, 1234567890000001',          // the old 16-digit shape
            '2201002, 009999552012026100002',     // another institution
            '2201003, 001001612012026100003',     // another programme
            '2201004, 001001552012026200004',     // a professional certificate
            '2201005, 001001552012026100005',     // right
        ]));

        expect($hasil['dicatat'])->toBe(1)
            ->and($hasil['ditolak'])->toHaveCount(4)
            ->and($hasil['ditolak'][0])->toContain('21 digit')
            ->and($hasil['ditolak'][1])->toContain('kode perguruan tinggi')->toContain('001001')
            ->and($hasil['ditolak'][2])->toContain('kode program studi')->toContain('55201')
            ->and($hasil['ditolak'][3])->toContain('sertifikat profesi');
    });

    it('menuntut tipe sertifikat (2) untuk lulusan prodi profesi', function () {
        $this->prodi->update(['kode' => '11901', 'jenjang' => EducationLevel::Profesi]);
        pesertaWisuda('2201001');
        pesertaWisuda('2201002');

        $hasil = $this->wisuda->catatNomorIjazah($this->periode, $this->staf, implode("\n", [
            '2201001, 001001119012026100001',
            '2201002, 001001119012026200002',
        ]));

        expect($hasil['dicatat'])->toBe(1)
            ->and($hasil['ditolak'][0])->toContain('sertifikat profesi (tipe 2)');
    });

    it('tidak memeriksa format nomor lulusan sebelum 2025, yang terbit dengan skema lama', function () {
        $peserta = pesertaWisuda('2201001');
        $peserta->yudisium->update(['tanggal_lulus' => '2024-08-30']);

        expect($this->wisuda->catatNomorIjazah($this->periode, $this->staf, '2201001, 001001202400123')['dicatat'])->toBe(1);
    });
});

describe('ACADEMIC_IJAZAH_SUMBER', function () {
    it('membaca pin lama sebagai pisn — usang, tetapi tetap aman', function () {
        config(['academic.ijazah.sumber' => 'pin']);

        expect(SumberNomorIjazah::dariConfig())->toBe(SumberNomorIjazah::Pisn)
            ->and(SumberNomorIjazah::memakaiAliasUsang())->toBeTrue()
            ->and($this->wisuda->penomoranLokal())->toBeFalse();
    });

    it('membaca nilai tak dikenal sebagai pisn, bukan sebagai pembangkit lokal', function () {
        config(['academic.ijazah.sumber' => 'PISN ']);
        expect(SumberNomorIjazah::dariConfig())->toBe(SumberNomorIjazah::Pisn);

        config(['academic.ijazah.sumber' => 'nasional']);
        expect(SumberNomorIjazah::dariConfig())->toBe(SumberNomorIjazah::Pisn);

        config(['academic.ijazah.sumber' => 'Lokal']);
        expect(SumberNomorIjazah::dariConfig())->toBe(SumberNomorIjazah::Lokal);
    });

    it('memberi tahu staf di layar wisuda bila .env masih memakai pin', function () {
        config(['academic.ijazah.sumber' => 'pin']);

        $this->actingAs($this->staf, 'staff')
            ->get('/admin/wisuda')
            ->assertOk()
            ->assertSee('ACADEMIC_IJAZAH_SUMBER=pin');
    });
});

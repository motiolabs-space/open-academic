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

function lulusan(string $status = 'ditetapkan'): Yudisium
{
    $test = test();

    $mahasiswa = Mahasiswa::factory()->create(['prodi_id' => $test->prodi->id]);

    return Yudisium::create([
        'mahasiswa_id' => $mahasiswa->id,
        'tahun_akademik_id' => $test->term->id,
        'total_sks' => 144,
        'ipk' => 3.50,
        'predikat' => 'Sangat Memuaskan',
        'status' => $status,
        'tanggal_lulus' => now(),
    ]);
}

describe('pendaftaran peserta', function () {
    it('memberi nomor urut berurutan', function () {
        $a = $this->wisuda->daftarkan($this->periode, lulusan());
        $b = $this->wisuda->daftarkan($this->periode, lulusan());

        expect($a->nomor_urut)->toBe(1)->and($b->nomor_urut)->toBe(2);
    });

    it('menolak lulusan yang kelulusannya belum ditetapkan', function () {
        // Menaruh nama di buku acara untuk gelar yang belum diberikan.
        expect(fn () => $this->wisuda->daftarkan($this->periode, lulusan('diajukan')))
            ->toThrow(AturanAkademikException::class, 'belum ditetapkan');
    });

    it('menolak pendaftaran saat pendaftaran ditutup', function () {
        $this->wisuda->tutupPendaftaran($this->periode);

        expect(fn () => $this->wisuda->daftarkan($this->periode->fresh(), lulusan()))
            ->toThrow(AturanAkademikException::class, 'sudah ditutup');
    });

    it('menolak mendaftarkan orang yang sama dua kali', function () {
        $yudisium = lulusan();
        $this->wisuda->daftarkan($this->periode, $yudisium);

        expect(fn () => $this->wisuda->daftarkan($this->periode, $yudisium))
            ->toThrow(AturanAkademikException::class, 'sudah terdaftar');
    });

    it('menolak orang yang sudah terdaftar pada periode lain', function () {
        $yudisium = lulusan();
        $this->wisuda->daftarkan($this->periode, $yudisium);

        $periodeLain = WisudaPeriode::create([
            'nama' => 'Wisuda Periode II 2026',
            'tanggal' => '2027-03-15',
            'is_pendaftaran_dibuka' => true,
        ]);

        expect(fn () => $this->wisuda->daftarkan($periodeLain, $yudisium))
            ->toThrow(AturanAkademikException::class, 'Wisuda Periode I 2026');
    });

    it('menghormati kuota periode', function () {
        $this->periode->update(['kuota' => 2]);

        $this->wisuda->daftarkan($this->periode->fresh(), lulusan());
        $this->wisuda->daftarkan($this->periode->fresh(), lulusan());

        expect(fn () => $this->wisuda->daftarkan($this->periode->fresh(), lulusan()))
            ->toThrow(AturanAkademikException::class, 'Kuota');
    });
});

describe('pendaftaran massal', function () {
    it('mendaftarkan seluruh lulusan yang belum masuk periode mana pun', function () {
        lulusan();
        lulusan();
        lulusan('diajukan'); // belum ditetapkan — tidak ikut

        $hasil = $this->wisuda->daftarkanMassal($this->periode);

        expect($hasil['didaftarkan'])->toBe(2)
            ->and(WisudaPeserta::count())->toBe(2);
    });

    it('berhenti saat kuota habis tanpa membatalkan yang sudah masuk', function () {
        $this->periode->update(['kuota' => 1]);
        lulusan();
        lulusan();

        $hasil = $this->wisuda->daftarkanMassal($this->periode->fresh());

        expect($hasil['didaftarkan'])->toBe(1)
            ->and($hasil['gagal'])->toHaveCount(1)
            ->and(WisudaPeserta::count())->toBe(1);
    });
});

describe('nomor ijazah', function () {
    it('mengikuti pola yang dikonfigurasi kampus', function () {
        $this->wisuda->daftarkan($this->periode, lulusan());

        $this->wisuda->terbitkanNomorIjazah($this->periode, $this->staf, '{tahun}/{prodi}/{urut}');

        expect(WisudaPeserta::first()->nomor_ijazah)->toBe('2026/55201/0001');
    });

    it('tidak pernah menerbitkan ulang nomor yang sudah ada', function () {
        // Nomor ijazah tercetak pada dokumen yang sudah di tangan orangnya.
        // Menerbitkannya ulang membuat dokumen itu tidak lagi cocok dengan
        // catatan kampus.
        $this->wisuda->daftarkan($this->periode, lulusan());
        $this->wisuda->terbitkanNomorIjazah($this->periode, $this->staf, '{tahun}/{prodi}/{urut}');

        $nomorAsli = WisudaPeserta::first()->nomor_ijazah;

        $kedua = $this->wisuda->terbitkanNomorIjazah($this->periode, $this->staf, 'POLA-BARU-{urut}');

        expect($kedua['diterbitkan'])->toBe(0)
            ->and($kedua['dilewati'])->toBe(1)
            ->and(WisudaPeserta::first()->nomor_ijazah)->toBe($nomorAsli);
    });

    it('menerbitkan hanya untuk peserta yang belum punya', function () {
        $this->wisuda->daftarkan($this->periode, lulusan());
        $this->wisuda->terbitkanNomorIjazah($this->periode, $this->staf, '{tahun}/{prodi}/{urut}');

        $this->wisuda->daftarkan($this->periode->fresh(), lulusan());

        $hasil = $this->wisuda->terbitkanNomorIjazah($this->periode, $this->staf, '{tahun}/{prodi}/{urut}');

        expect($hasil['diterbitkan'])->toBe(1)->and($hasil['dilewati'])->toBe(1);
    });
});

describe('pembatalan peserta', function () {
    it('menolak mengeluarkan peserta yang sudah punya nomor ijazah', function () {
        $this->wisuda->daftarkan($this->periode, lulusan());
        $this->wisuda->terbitkanNomorIjazah($this->periode, $this->staf, '{tahun}/{prodi}/{urut}');

        expect(fn () => $this->wisuda->batalkan(WisudaPeserta::first()))
            ->toThrow(AturanAkademikException::class, 'nomor ijazah');
    });

    it('mengizinkan mengeluarkan peserta yang belum bernomor', function () {
        $this->wisuda->daftarkan($this->periode, lulusan());

        $this->wisuda->batalkan(WisudaPeserta::first());

        expect(WisudaPeserta::count())->toBe(0);
    });
});

describe('layar wisuda', function () {
    it('merender layar', function () {
        $this->wisuda->daftarkan($this->periode, lulusan());
        lulusan(); // menunggu periode

        $this->actingAs($this->staf, 'staff')->get('/admin/wisuda')->assertOk();
    });

    it('membuat periode lewat layar', function () {
        $this->actingAs($this->staf, 'staff')->post('/admin/wisuda/periode', [
            'nama' => 'Wisuda Periode II',
            'tanggal' => '2027-03-15',
        ])->assertRedirect()->assertSessionHasNoErrors();

        expect(WisudaPeriode::where('nama', 'Wisuda Periode II')->exists())->toBeTrue();
    });

    it('menerbitkan nomor ijazah lewat layar', function () {
        $this->wisuda->daftarkan($this->periode, lulusan());

        $this->actingAs($this->staf, 'staff')
            ->post("/admin/wisuda/periode/{$this->periode->uuid}/ijazah", ['pola' => '{tahun}/{prodi}/{urut}'])
            ->assertSessionHas('sukses');

        expect(WisudaPeserta::first()->nomor_ijazah)->not->toBeNull();
    });

    it('menolak staf tanpa izin wisuda', function () {
        $keuangan = Staff::factory()->create();
        $keuangan->assignRole('keuangan');

        $this->actingAs($keuangan, 'staff')->get('/admin/wisuda')->assertForbidden();
    });
});

<?php

declare(strict_types=1);

use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Keuangan\Tagihan;
use App\Models\Keuangan\Tarif;
use App\Models\Sdm\Staff;
use App\Services\Keuangan\PenerbitanTagihanService;
use App\Services\Keuangan\TarifResolver;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->term = TahunAkademik::factory()->berjalan()->create([
        'kode' => '20261', 'tahun_mulai' => 2026, 'is_active' => true,
    ]);

    $this->prodi = Prodi::factory()->create();
    $this->lain = Prodi::factory()->create();

    $this->mahasiswa = Mahasiswa::factory()->create([
        'prodi_id' => $this->prodi->id,
        'angkatan' => 2026,
        'jalur_masuk' => 'Reguler',
        'golongan_ukt' => 'III',
        'status' => 'A',
    ]);

    $this->staf = Staff::factory()->create();
    $this->staf->assignRole('keuangan');

    $this->resolver = app(TarifResolver::class);
});

function tarif(array $atribut): Tarif
{
    return Tarif::create([
        'komponen' => 'ukt',
        'nama' => 'UKT',
        'nominal' => 1_000_000,
        'is_active' => true,
        ...$atribut,
    ]);
}

describe('baris paling spesifik yang menang', function () {
    it('tidak menjumlahkan tarif umum dengan penimpanya', function () {
        // Inilah bug yang diperbaiki. Menjumlahkan keduanya membuat mahasiswa
        // ditagih 12 juta dan diminta membayarnya, tanpa ada yang menandai.
        tarif(['nama' => 'UKT umum', 'nominal' => 5_000_000]);
        tarif(['nama' => 'UKT prodi', 'nominal' => 7_000_000, 'prodi_id' => $this->prodi->id]);

        expect($this->resolver->total($this->mahasiswa, $this->term))->toBe(7_000_000);
    });

    it('memilih baris berdimensi paling banyak', function () {
        tarif(['nominal' => 5_000_000]);
        tarif(['nominal' => 6_000_000, 'prodi_id' => $this->prodi->id]);
        tarif(['nominal' => 8_000_000, 'prodi_id' => $this->prodi->id, 'angkatan' => 2026, 'golongan_ukt' => 'III']);

        expect($this->resolver->total($this->mahasiswa, $this->term))->toBe(8_000_000);
    });

    it('memenangkan baris terbaru saat spesifisitasnya seri', function () {
        tarif(['nominal' => 5_000_000, 'prodi_id' => $this->prodi->id]);
        $koreksi = tarif(['nominal' => 6_500_000, 'prodi_id' => $this->prodi->id]);

        // Jadwal biaya yang dimasukkan belakangan adalah koreksinya.
        expect($this->resolver->total($this->mahasiswa, $this->term))->toBe(6_500_000)
            ->and($this->resolver->untuk($this->mahasiswa, $this->term)->first()->id)->toBe($koreksi->id);
    });

    it('menjumlahkan antar komponen yang berbeda', function () {
        // Yang tidak dijumlahkan adalah baris dalam satu komponen; komponen
        // berbeda memang dijumlahkan.
        tarif(['komponen' => 'ukt', 'nominal' => 5_000_000]);
        tarif(['komponen' => 'praktikum', 'nama' => 'Praktikum', 'nominal' => 750_000]);

        expect($this->resolver->total($this->mahasiswa, $this->term))->toBe(5_750_000)
            ->and($this->resolver->untuk($this->mahasiswa, $this->term))->toHaveCount(2);
    });
});

describe('dimensi pencocokan', function () {
    it('mengabaikan tarif milik prodi lain', function () {
        tarif(['nominal' => 9_000_000, 'prodi_id' => $this->lain->id]);

        expect($this->resolver->untuk($this->mahasiswa, $this->term))->toBeEmpty();
    });

    it('mencocokkan golongan UKT mahasiswa', function () {
        tarif(['nominal' => 3_000_000, 'golongan_ukt' => 'I']);
        tarif(['nominal' => 5_000_000, 'golongan_ukt' => 'III']);
        tarif(['nominal' => 9_000_000, 'golongan_ukt' => 'VIII']);

        // UKT bersifat berjenjang menurut kemampuan ekonomi; menagih semua
        // orang sama rata membatalkan seluruh kebijakannya.
        expect($this->resolver->total($this->mahasiswa, $this->term))->toBe(5_000_000);
    });

    it('mencocokkan jalur masuk', function () {
        tarif(['nominal' => 8_000_000, 'jalur_masuk' => 'Mandiri']);

        expect($this->resolver->untuk($this->mahasiswa, $this->term))->toBeEmpty();
    });

    it('mengabaikan tarif yang masa berlakunya sudah lewat', function () {
        // Scope berlakuPada() sudah ada sejak awal; pemanggilnya yang tidak
        // pernah memakainya, sehingga jadwal biaya lama tetap ditagihkan.
        tarif(['nominal' => 4_000_000, 'term_berlaku_sampai' => '20252']);

        expect($this->resolver->untuk($this->mahasiswa, $this->term))->toBeEmpty();
    });

    it('mengabaikan tarif yang belum mulai berlaku', function () {
        tarif(['nominal' => 4_000_000, 'term_berlaku_dari' => '20271']);

        expect($this->resolver->untuk($this->mahasiswa, $this->term))->toBeEmpty();
    });

    it('memakai tarif yang masa berlakunya mencakup semester ini', function () {
        tarif(['nominal' => 4_000_000, 'term_berlaku_dari' => '20251', 'term_berlaku_sampai' => '20272']);

        expect($this->resolver->total($this->mahasiswa, $this->term))->toBe(4_000_000);
    });

    it('mengabaikan tarif nonaktif', function () {
        tarif(['nominal' => 4_000_000, 'is_active' => false]);

        expect($this->resolver->untuk($this->mahasiswa, $this->term))->toBeEmpty();
    });
});

describe('penerbitan tagihan memakai aturan yang sama', function () {
    it('menerbitkan tagihan sebesar baris yang menang, bukan jumlahnya', function () {
        tarif(['nama' => 'UKT umum', 'nominal' => 5_000_000]);
        tarif(['nama' => 'UKT prodi', 'nominal' => 7_000_000, 'prodi_id' => $this->prodi->id]);

        app(PenerbitanTagihanService::class)->terbitkan($this->term);

        $tagihan = Tagihan::where('mahasiswa_id', $this->mahasiswa->id)->firstOrFail();

        expect((int) $tagihan->total)->toBe(7_000_000)
            ->and($tagihan->item()->count())->toBe(1);
    });

    it('tidak menagih tarif yang sudah kedaluwarsa', function () {
        tarif(['nominal' => 4_000_000, 'term_berlaku_sampai' => '20252']);

        $hasil = app(PenerbitanTagihanService::class)->terbitkan($this->term);

        expect($hasil['terbit'])->toBe(0)
            ->and($hasil['tanpa_tarif'])->toBe(1);
    });
});

describe('rincian untuk layar', function () {
    it('menyebutkan baris yang dikalahkan', function () {
        tarif(['nama' => 'UKT umum', 'nominal' => 5_000_000]);
        tarif(['nama' => 'UKT prodi', 'nominal' => 7_000_000, 'prodi_id' => $this->prodi->id]);

        $rincian = $this->resolver->rincian($this->mahasiswa, $this->term);

        expect($rincian)->toHaveCount(1)
            ->and($rincian->first()['terpilih']->nama)->toBe('UKT prodi')
            ->and($rincian->first()['dikalahkan'])->toHaveCount(1)
            ->and($rincian->first()['dikalahkan']->first()->nama)->toBe('UKT umum');
    });
});

describe('layar matriks tarif', function () {
    it('merender matriks', function () {
        tarif(['nominal' => 5_000_000]);

        $this->actingAs($this->staf, 'staff')->get('/admin/tarif')->assertOk();
    });

    it('menampilkan simulasi untuk NIM yang nyata', function () {
        tarif(['nama' => 'UKT umum', 'nominal' => 5_000_000]);
        tarif(['nama' => 'UKT prodi', 'nominal' => 7_000_000, 'prodi_id' => $this->prodi->id]);

        $this->actingAs($this->staf, 'staff')
            ->get('/admin/tarif?simulasi_nim='.$this->mahasiswa->nim.'&simulasi_term='.$this->term->id)
            ->assertOk()
            ->assertSee('UKT prodi')
            ->assertSee('dikalahkan');
    });

    it('menambah baris tarif lewat layar', function () {
        $this->actingAs($this->staf, 'staff')->post('/admin/tarif', [
            'komponen' => 'ukt',
            'nama' => 'UKT Golongan III',
            'nominal' => 5_000_000,
            'golongan_ukt' => 'III',
        ])->assertRedirect()->assertSessionHasNoErrors();

        expect(Tarif::where('golongan_ukt', 'III')->exists())->toBeTrue();
    });

    it('menolak golongan UKT di luar I sampai VIII', function () {
        $this->actingAs($this->staf, 'staff')->post('/admin/tarif', [
            'komponen' => 'ukt',
            'nama' => 'UKT',
            'nominal' => 5_000_000,
            'golongan_ukt' => 'IX',
        ])->assertSessionHasErrors('golongan_ukt');
    });

    it('menolak masa berlaku yang berakhir sebelum dimulai', function () {
        $this->actingAs($this->staf, 'staff')->post('/admin/tarif', [
            'komponen' => 'ukt',
            'nama' => 'UKT',
            'nominal' => 5_000_000,
            'term_berlaku_dari' => '20262',
            'term_berlaku_sampai' => '20261',
        ])->assertSessionHasErrors('term_berlaku_sampai');
    });

    it('menolak staf tanpa izin keuangan', function () {
        $baak = Staff::factory()->create();
        $baak->assignRole('baak');

        $this->actingAs($baak, 'staff')->get('/admin/tarif')->assertForbidden();
    });
});

<?php

declare(strict_types=1);

use App\Enums\JenisUnitKerja;
use App\Enums\SemesterType;
use App\Exceptions\AturanAkademikException;
use App\Models\Akademik\TahunAkademik;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\Staff;
use App\Models\Sdm\UnitKerja;
use App\Services\Sdm\UnitKerjaService;
use Database\Seeders\RolePermissionSeeder;

/**
 * Unit kerja.
 *
 * A tree stored as parent pointers has one structural failure mode — a cycle —
 * and it is silent on write. Most of these tests are about refusing it.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->unit = app(UnitKerjaService::class);

    $this->staf = Staff::factory()->create();
    $this->staf->assignRole('super-admin');

    // Layar admin dijaga EnsureTermIsActive dan menjawab 503 tanpanya.
    TahunAkademik::factory()->term(2026, SemesterType::Ganjil)->berjalan()->aktif()->create();
});

function unitUji(string $kode, ?UnitKerja $induk = null): UnitKerja
{
    return UnitKerja::create([
        'kode' => $kode,
        'nama' => 'Unit '.$kode,
        'jenis' => JenisUnitKerja::Struktural,
        'parent_id' => $induk?->id,
    ]);
}

describe('lingkaran ditolak', function () {
    it('menolak unit menjadi induk dirinya sendiri', function () {
        $unit = unitUji('A');

        expect(fn () => $this->unit->simpan($unit, ['parent_id' => $unit->id]))
            ->toThrow(AturanAkademikException::class, 'induk dirinya sendiri');
    });

    it('menolak induk yang merupakan turunannya sendiri', function () {
        /*
         * Ini yang tidak terlihat. Menaruh A di bawah C, sementara C sudah di
         * bawah B yang di bawah A, membuat setiap penelusuran berjalan
         * selamanya — dan tidak ada yang gagal saat menyimpannya.
         */
        $a = unitUji('A');
        $b = unitUji('B', $a);
        $c = unitUji('C', $b);

        expect(fn () => $this->unit->simpan($a, ['parent_id' => $c->id]))
            ->toThrow(AturanAkademikException::class, 'lingkaran');
    });

    it('mengizinkan pemindahan yang sah', function () {
        $a = unitUji('A');
        $b = unitUji('B', $a);
        $lain = unitUji('LAIN');

        expect($this->unit->simpan($b, ['parent_id' => $lain->id])->parent_id)->toBe($lain->id);
    });
});

describe('kepala unit', function () {
    it('menolak kepala dari staf dan dosen sekaligus', function () {
        // Dua-duanya terisi bukan jawaban yang lebih kaya, melainkan dua
        // jawaban — dan tiap layar harus memilih salah satu, berbeda-beda.
        $dosen = Dosen::factory()->create();

        expect(fn () => $this->unit->buat([
            'kode' => 'DUA',
            'nama' => 'Unit Dua Kepala',
            'jenis' => JenisUnitKerja::Struktural->value,
            'kepala_staff_id' => $this->staf->id,
            'kepala_dosen_id' => $dosen->id,
        ]))->toThrow(AturanAkademikException::class, 'satu kepala unit saja');
    });

    it('menerima kepala dari dosen saja', function () {
        // Dekan itu dosen; kepala biro itu staf. Keduanya harus mungkin.
        $dosen = Dosen::factory()->create();

        $unit = $this->unit->buat([
            'kode' => 'FT',
            'nama' => 'Fakultas Teknik',
            'jenis' => JenisUnitKerja::Akademik->value,
            'kepala_dosen_id' => $dosen->id,
        ]);

        expect($unit->kepala()?->id)->toBe($dosen->id);
    });
});

describe('menonaktifkan', function () {
    it('menolak selama masih ada staf di dalamnya', function () {
        /*
         * Menonaktifkan diam-diam meninggalkan staf yang menunjuk unit yang
         * tidak muncul di daftar mana pun — terbaca sebagai "tanpa unit" di
         * setiap layar. Persis kegagalan tak terlihat yang dulu disebabkan
         * kolom teks bebas.
         */
        $unit = unitUji('BAAK');
        $this->unit->pindahkanStaf($this->staf, $unit);

        expect(fn () => $this->unit->nonaktifkan($unit->refresh()))
            ->toThrow(AturanAkademikException::class, 'Pindahkan mereka lebih dulu');
    });

    it('menolak selama masih membawahi unit aktif', function () {
        $induk = unitUji('BIRO');
        unitUji('BAGIAN', $induk);

        expect(fn () => $this->unit->nonaktifkan($induk))
            ->toThrow(AturanAkademikException::class, 'membawahi');
    });

    it('menolak penempatan ke unit yang sudah nonaktif', function () {
        $unit = unitUji('LAMA');
        $this->unit->nonaktifkan($unit);

        expect(fn () => $this->unit->pindahkanStaf($this->staf, $unit->refresh()))
            ->toThrow(AturanAkademikException::class, 'tidak aktif');
    });
});

describe('rekap bertingkat', function () {
    it('menghitung staf termasuk seluruh bawahannya', function () {
        // Angka yang sebenarnya ditanyakan seorang kepala biro.
        $biro = unitUji('BIRO');
        $bagian = unitUji('BAGIAN', $biro);
        $sub = unitUji('SUB', $bagian);

        $this->unit->pindahkanStaf($this->staf, $biro);
        $this->unit->pindahkanStaf(Staff::factory()->create(), $bagian);
        $this->unit->pindahkanStaf(Staff::factory()->create(), $sub);

        $pohon = $this->unit->pohon();

        expect($this->unit->jumlahStafTermasukBawahan($pohon->firstWhere('id', $biro->id), $pohon))->toBe(3)
            ->and($this->unit->jumlahStafTermasukBawahan($pohon->firstWhere('id', $bagian->id), $pohon))->toBe(2)
            ->and($this->unit->jumlahStafTermasukBawahan($pohon->firstWhere('id', $sub->id), $pohon))->toBe(1);
    });

    it('menyusun jalur dari puncak ke unitnya', function () {
        $biro = unitUji('BIRO');
        $bagian = unitUji('BAGIAN', $biro);

        $pohon = $this->unit->pohon();

        expect($pohon->firstWhere('id', $bagian->id)->jalur($pohon))->toBe('Unit BIRO › Unit BAGIAN');
    });
});

describe('layar', function () {
    it('menampilkan pohon beserta rekapnya', function () {
        $biro = unitUji('BIRO');
        unitUji('BAGIAN', $biro);

        $this->actingAs($this->staf, 'staff')
            ->get('/admin/unit-kerja')
            ->assertOk()
            ->assertSee('Unit BIRO')
            ->assertSee('Unit BIRO › Unit BAGIAN', false);
    });

    it('menolak staf tanpa izin SDM mengubah struktur', function () {
        $keuangan = Staff::factory()->create();
        $keuangan->assignRole('keuangan');

        $this->actingAs($keuangan, 'staff')
            ->post('/admin/unit-kerja', [
                'kode' => 'X',
                'nama' => 'Unit X',
                'jenis' => JenisUnitKerja::Struktural->value,
            ])
            ->assertForbidden();
    });
});

<?php

declare(strict_types=1);

use App\Enums\JenisUnitKerja;
use App\Exceptions\AturanAkademikException;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\PangkatDosen;
use App\Models\Sdm\UnitKerja;
use App\Services\Sdm\RiwayatKepegawaianService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Kepegawaian mendalam.
 *
 * Only rank and posting carry rules; the rest are flat histories. So these
 * tests are about the two pointers that something else reads.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->kepegawaian = app(RiwayatKepegawaianService::class);
    $this->dosen = Dosen::factory()->create();
});

function unitKepegawaian(string $kode): UnitKerja
{
    return UnitKerja::create([
        'kode' => $kode,
        'nama' => 'Unit '.$kode,
        'jenis' => JenisUnitKerja::Struktural,
    ]);
}

describe('pangkat', function () {
    it('menandai pangkat baru sebagai yang berlaku', function () {
        $this->kepegawaian->naikPangkat($this->dosen, [
            'pangkat' => 'Penata Muda',
            'golongan' => 'III/a',
            'tmt' => '2020-04-01',
        ]);

        expect($this->kepegawaian->pangkatBerlaku($this->dosen)?->golongan)->toBe('III/a');
    });

    it('memensiunkan pangkat sebelumnya saat naik', function () {
        $lama = $this->kepegawaian->naikPangkat($this->dosen, [
            'pangkat' => 'Penata Muda', 'golongan' => 'III/a', 'tmt' => '2020-04-01',
        ]);

        $this->kepegawaian->naikPangkat($this->dosen, [
            'pangkat' => 'Penata', 'golongan' => 'III/c', 'tmt' => '2024-04-01',
        ]);

        expect($lama->refresh()->berlaku())->toBeFalse()
            ->and($this->kepegawaian->pangkatBerlaku($this->dosen)?->golongan)->toBe('III/c')
            ->and(PangkatDosen::where('dosen_id', $this->dosen->id)->count())->toBe(2);
    });

    it('membiarkan basis data menolak dua pangkat berlaku sekaligus', function () {
        /*
         * Penjaganya ada di kolom, bukan hanya di service. Kode yang menyisipkan
         * langsung — seeder, perbaikan manual, migrasi data — tetap tertahan.
         */
        $this->kepegawaian->naikPangkat($this->dosen, [
            'pangkat' => 'Penata Muda', 'golongan' => 'III/a', 'tmt' => '2020-04-01',
        ]);

        expect(fn () => PangkatDosen::create([
            'dosen_id' => $this->dosen->id,
            'pangkat' => 'Pembina',
            'golongan' => 'IV/a',
            'tmt' => '2025-04-01',
            'dosen_aktif_id' => $this->dosen->id,
        ]))->toThrow(UniqueConstraintViolationException::class);
    });
});

describe('mutasi', function () {
    it('memindahkan penunjuk unit bersama catatan riwayatnya', function () {
        $asal = unitKepegawaian('LAMA');
        $tujuan = unitKepegawaian('BARU');

        $this->dosen->update(['unit_kerja_id' => $asal->id]);

        $mutasi = $this->kepegawaian->catatMutasi($this->dosen, [
            'jenis' => 'pindah',
            'unit_tujuan_id' => $tujuan->id,
            'tmt' => '2026-01-01',
        ]);

        expect($this->dosen->refresh()->unit_kerja_id)->toBe($tujuan->id)
            ->and($mutasi->unit_asal_id)->toBe($asal->id);
    });

    it('mengambil unit asal dari penunjuk, bukan dari formulir', function () {
        // Formulir bisa dikirim setelah orang lain memindahkannya lebih dulu.
        $asal = unitKepegawaian('SEBENARNYA');
        $tujuan = unitKepegawaian('TUJUAN');

        $this->dosen->update(['unit_kerja_id' => $asal->id]);

        $mutasi = $this->kepegawaian->catatMutasi($this->dosen, [
            'jenis' => 'pindah',
            'unit_tujuan_id' => $tujuan->id,
            'tmt' => '2026-01-01',
        ]);

        expect($mutasi->unit_asal_id)->toBe($asal->id);
    });

    it('mengosongkan unit ketika seseorang keluar', function () {
        /*
         * Yang sudah keluar tidak lagi tercatat di biro lamanya — itulah cara
         * sebuah rekap terus menghitung orang yang sudah mengundurkan diri.
         */
        $unit = unitKepegawaian('BIRO');
        $this->dosen->update(['unit_kerja_id' => $unit->id]);

        $this->kepegawaian->catatMutasi($this->dosen, [
            'jenis' => 'keluar',
            'tmt' => '2026-06-30',
        ]);

        expect($this->dosen->refresh()->unit_kerja_id)->toBeNull();
    });

    it('menolak pindah tanpa unit tujuan', function () {
        expect(fn () => $this->kepegawaian->catatMutasi($this->dosen, [
            'jenis' => 'pindah',
            'tmt' => '2026-01-01',
        ]))->toThrow(AturanAkademikException::class, 'unit tujuan');
    });

    it('menolak unit tujuan yang sudah tidak aktif', function () {
        $unit = unitKepegawaian('PENSIUN');
        $unit->update(['is_active' => false]);

        expect(fn () => $this->kepegawaian->catatMutasi($this->dosen, [
            'jenis' => 'pindah',
            'unit_tujuan_id' => $unit->id,
            'tmt' => '2026-01-01',
        ]))->toThrow(AturanAkademikException::class, 'tidak aktif');
    });
});

describe('berkas kepegawaian', function () {
    it('memisahkan penghargaan dan sanksi menjadi dua daftar', function () {
        // Satu tabel karena kolomnya sama; tidak pernah satu angka, karena
        // penghargaan tidak menghapus sanksi.
        $this->dosen->penghargaanSanksi()->createMany([
            ['jenis' => 'penghargaan', 'nama' => 'Dosen Berprestasi', 'tanggal' => '2025-08-17'],
            ['jenis' => 'sanksi', 'nama' => 'Teguran tertulis', 'tanggal' => '2024-03-01'],
        ]);

        $berkas = $this->kepegawaian->berkas($this->dosen);

        expect($berkas['penghargaan'])->toHaveCount(1)
            ->and($berkas['sanksi'])->toHaveCount(1)
            ->and(array_keys($berkas))->not->toContain('poin');
    });

    it('menyebutkan pangkat yang berlaku secara terpisah dari riwayatnya', function () {
        $this->kepegawaian->naikPangkat($this->dosen, [
            'pangkat' => 'Penata Muda', 'golongan' => 'III/a', 'tmt' => '2020-04-01',
        ]);
        $this->kepegawaian->naikPangkat($this->dosen, [
            'pangkat' => 'Penata', 'golongan' => 'III/c', 'tmt' => '2024-04-01',
        ]);

        $berkas = $this->kepegawaian->berkas($this->dosen->refresh());

        expect($berkas['pangkatBerlaku']?->golongan)->toBe('III/c')
            ->and($berkas['pangkat'])->toHaveCount(2);
    });
});

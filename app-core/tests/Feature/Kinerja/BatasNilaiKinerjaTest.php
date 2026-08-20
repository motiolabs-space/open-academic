<?php

declare(strict_types=1);

use App\Enums\JenisUnitKerja;
use App\Enums\SemesterType;
use App\Enums\StatusPeriodeKinerja;
use App\Enums\SumberRealisasi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kinerja\PeriodeKinerja;
use App\Models\Kinerja\SasaranKinerja;
use App\Models\Kinerja\UkuranKinerja;
use App\Models\Sdm\Staff;
use App\Models\Sdm\UnitKerja;
use Database\Seeders\RolePermissionSeeder;

/**
 * Target dan realisasi kinerja dibatasi pada apa yang muat di kolomnya.
 *
 * `target` dan `nilai` adalah DECIMAL(12,2). MySQL di luar mode ketat tidak
 * menolak angka yang melebihinya — ia memotongnya diam-diam menjadi
 * 9.999.999.999,99. Diuji langsung pada MariaDB XAMPP sebelum perbaikan ini:
 * INSERT 1e15 tersimpan sebagai 9999999999.99, tanpa galat, tanpa peringatan.
 *
 * Tesnya berjalan di SQLite, yang justru TIDAK memotong — jadi yang dipaku di
 * sini adalah validasinya, bukan perilaku basis datanya. Itu memang yang benar:
 * aplikasi tidak boleh bergantung pada mode ketat yang mungkin tidak menyala di
 * server kampus.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    TahunAkademik::factory()->term(2026, SemesterType::Ganjil)->berjalan()->aktif()->create();

    $this->staf = Staff::factory()->create();
    $this->staf->assignRole('super-admin');

    $unit = UnitKerja::create([
        'kode' => 'REKTORAT',
        'nama' => 'Rektorat',
        'jenis' => JenisUnitKerja::Struktural,
    ]);

    $periode = PeriodeKinerja::create([
        'nama' => 'Rencana Kinerja 2026',
        'tahun' => 2026,
        'mulai' => '2026-01-01',
        'selesai' => '2026-12-31',
        'status' => StatusPeriodeKinerja::Berjalan,
    ]);

    $this->sasaran = SasaranKinerja::create([
        'periode_kinerja_id' => $periode->id,
        'unit_kerja_id' => $unit->id,
        'judul' => 'Sasaran uji',
        'urutan' => 1,
    ]);
});

/** @return array<string, mixed> */
function muatanUkuran(mixed $target): array
{
    return [
        'nama' => 'Ukuran uji',
        'sumber_realisasi' => SumberRealisasi::Dilaporkan->value,
        'target' => $target,
    ];
}

describe('batas target', function () {
    it('menolak target yang melebihi daya tampung kolom', function () {
        // Tanpa batas ini MySQL menyimpannya sebagai 9.999.999.999,99 —
        // angka yang tidak pernah diketik siapa pun.
        $this->actingAs($this->staf, 'staff')
            ->post("/admin/kinerja/sasaran/{$this->sasaran->uuid}/ukuran", muatanUkuran('1000000000000000'))
            ->assertSessionHasErrors('target');
    });

    it('menolak target negatif', function () {
        // Kedelapan indikator di config/kinerja.php adalah cacahan, rerata,
        // atau persentase. Tak satu pun dapat bernilai negatif.
        $this->actingAs($this->staf, 'staff')
            ->post("/admin/kinerja/sasaran/{$this->sasaran->uuid}/ukuran", muatanUkuran('-500'))
            ->assertSessionHasErrors('target');
    });

    it('menerima target di batas atas persis', function () {
        // Batasnya daya tampung kolom, bukan angka bulat yang enak dilihat —
        // jadi nilai tepat di tepinya harus lolos.
        $this->actingAs($this->staf, 'staff')
            ->post("/admin/kinerja/sasaran/{$this->sasaran->uuid}/ukuran", muatanUkuran('9999999999.99'))
            ->assertSessionHasNoErrors();
    });

    it('menerima target wajar', function () {
        $this->actingAs($this->staf, 'staff')
            ->post("/admin/kinerja/sasaran/{$this->sasaran->uuid}/ukuran", muatanUkuran('500'))
            ->assertSessionHasNoErrors();
    });
});

describe('tanggal capaian', function () {
    /**
     * Periode kinerja dibaca sebagai deret waktu. Satu titik di luar rentangnya
     * membuat seluruh deret berbohong tanpa pernah terlihat salah — jadi
     * penjaganya di service, bukan di formulir.
     */
    it('menolak capaian bertanggal di luar periodenya', function () {
        $this->actingAs($this->staf, 'staff')
            ->post("/admin/kinerja/sasaran/{$this->sasaran->uuid}/ukuran", muatanUkuran('100'));

        $ukuran = UkuranKinerja::query()->latest('id')->firstOrFail();

        $this->actingAs($this->staf, 'staff')
            ->post("/admin/kinerja/ukuran/{$ukuran->uuid}/capaian", [
                'nilai' => '50',
                'tanggal' => '2019-06-01',
            ])
            ->assertSessionHas('galat', fn (string $p): bool => str_contains($p, 'di luar periode'));
    });

    it('menerima capaian pada hari terakhir periode', function () {
        // Dibandingkan sebagai tanggal, bukan waktu: `selesai` di-cast ke tengah
        // malam, jadi hari terakhir akan tertolak bila jamnya ikut dihitung.
        $this->actingAs($this->staf, 'staff')
            ->post("/admin/kinerja/sasaran/{$this->sasaran->uuid}/ukuran", muatanUkuran('100'));

        $ukuran = UkuranKinerja::query()->latest('id')->firstOrFail();

        $this->actingAs($this->staf, 'staff')
            ->post("/admin/kinerja/ukuran/{$ukuran->uuid}/capaian", [
                'nilai' => '50',
                'tanggal' => '2026-12-31',
            ])
            ->assertSessionHas('sukses');
    });
});

describe('batas realisasi', function () {
    it('menolak realisasi yang melebihi daya tampung kolom', function () {
        $this->actingAs($this->staf, 'staff')
            ->post("/admin/kinerja/sasaran/{$this->sasaran->uuid}/ukuran", muatanUkuran('100'));

        $ukuran = UkuranKinerja::query()->latest('id')->firstOrFail();

        $this->actingAs($this->staf, 'staff')
            ->post("/admin/kinerja/ukuran/{$ukuran->uuid}/capaian", [
                'nilai' => '1000000000000000',
                'tanggal' => '2026-06-01',
            ])
            ->assertSessionHasErrors('nilai');
    });
});

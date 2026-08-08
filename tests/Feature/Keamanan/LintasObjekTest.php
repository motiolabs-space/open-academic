<?php

declare(strict_types=1);

use App\Models\Akademik\KelasKuliah;
use App\Models\Akademik\PertemuanKelas;
use App\Models\Akademik\TahunAkademik;
use App\Models\Sdm\Dosen;
use Database\Seeders\RolePermissionSeeder;

/**
 * Authorisation is only as good as the object it was asked about.
 *
 * These cases cover routes that take two independently-resolved parameters,
 * where the permission is checked on one of them and the write lands on the
 * other.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

/** @return array{0: Dosen, 1: KelasKuliah, 2: KelasKuliah} */
function dosenDenganKelas(): array
{
    // Both classes must sit in the same term: the portal is gated on an active
    // one, and two factory-made terms would collide on the PDDIKTI code.
    $term = TahunAkademik::factory()->berjalan()->create(['is_active' => true]);

    $dosen = Dosen::factory()->create();
    $dosen->assignRole('dosen');

    $milik = KelasKuliah::factory()->create(['tahun_akademik_id' => $term->id]);
    $milik->dosen()->attach($dosen->id, ['peran' => 'pengampu']);

    // Taught by somebody else entirely.
    $asing = KelasKuliah::factory()->create(['tahun_akademik_id' => $term->id]);
    $asing->dosen()->attach(Dosen::factory()->create()->id, ['peran' => 'pengampu']);

    return [$dosen, $milik, $asing];
}

function pertemuanUntuk(KelasKuliah $kelas): PertemuanKelas
{
    return PertemuanKelas::create([
        'kelas_kuliah_id' => $kelas->id,
        'pertemuan_ke' => 1,
        'tanggal' => now()->toDateString(),
    ]);
}

it('menolak penyimpanan presensi untuk pertemuan kelas lain', function () {
    [$dosen, $milik, $asing] = dosenDenganKelas();

    $pertemuanAsing = pertemuanUntuk($asing);

    $this->actingAs($dosen, 'dosen')
        ->post("/dosen/presensi/{$milik->uuid}/{$pertemuanAsing->uuid}", ['status' => []])
        ->assertNotFound();

    expect($pertemuanAsing->fresh()->is_terlaksana)->toBeFalse();
});

it('menolak pembukaan sesi QR pada pertemuan kelas lain', function () {
    [$dosen, $milik, $asing] = dosenDenganKelas();

    $pertemuanAsing = pertemuanUntuk($asing);

    $this->actingAs($dosen, 'dosen')
        ->post("/dosen/presensi/{$milik->uuid}/{$pertemuanAsing->uuid}/qr")
        ->assertNotFound();

    expect($pertemuanAsing->fresh()->qr_token)->toBeNull();
});

it('mengizinkan presensi pada pertemuan kelas sendiri', function () {
    [$dosen, $milik] = dosenDenganKelas();

    $pertemuanSendiri = pertemuanUntuk($milik);

    $this->actingAs($dosen, 'dosen')
        ->post("/dosen/presensi/{$milik->uuid}/{$pertemuanSendiri->uuid}", ['status' => []])
        ->assertRedirect();
});

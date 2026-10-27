<?php

declare(strict_types=1);

use App\Enums\AlasanAmbiguAdopsi;
use App\Enums\StatusAdopsiFeeder;
use App\Models\Akademik\Prodi;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Services\Feeder\Contracts\FeederClientInterface;
use App\Services\Feeder\FakeFeederClient;
use App\Services\Feeder\FeederAdopsi;
use App\Services\Feeder\PencocokMahasiswaFeeder;

/**
 * Pencocok bersama adopsi (#44) dan klaim mahasiswa lampau (#44b).
 *
 * Kasus lengkapnya diuji lewat kedua layanan itu; di sini hanya kontrak
 * method publiknya, supaya pemakai ketiga tidak bergantung pada perilaku
 * yang kebetulan.
 */
beforeEach(function () {
    $this->pencocok = new PencocokMahasiswaFeeder;
    $this->prodi = Prodi::factory()->create(['kode_pddikti' => 'prodi-ti-0001']);
});

function kandidatUji(string $reg, ?string $nim, ?string $nik, ?string $nama = 'Ayu Lestari'): array
{
    return ['id_registrasi_mahasiswa' => $reg, 'id_mahasiswa' => 'mhs-'.$reg, 'nim' => $nim, 'nik' => $nik, 'nama' => $nama, 'tanggal_lahir' => null];
}

it('mencocokkan NIM dan NIK, dan meragukan selebihnya', function () {
    $ayu = Mahasiswa::factory()->create(['prodi_id' => $this->prodi->id, 'nim' => '2024001', 'nik' => '3201010101010001', 'nama' => 'Ayu Lestari']);
    $budi = Mahasiswa::factory()->create(['prodi_id' => $this->prodi->id, 'nim' => '2024002', 'nik' => '3201010101010002', 'nama' => 'Budi']);
    $cici = Mahasiswa::factory()->create(['prodi_id' => $this->prodi->id, 'nim' => '2024003', 'nik' => null, 'nama' => 'Cici']);

    $usulan = collect($this->pencocok->cocokkan(collect([$ayu, $budi, $cici]), [
        'r1' => kandidatUji('r1', '2024001', '3201010101010001', 'AYU LESTARI'),
        'r2' => kandidatUji('r2', '2024002', '3201010101019999', 'Budi'),
    ]))->keyBy(fn ($u) => $u['mahasiswa']->nim);

    expect($usulan['2024001']['status'])->toBe(StatusAdopsiFeeder::Diadopsi)
        ->and($usulan['2024002']['status'])->toBe(StatusAdopsiFeeder::Ambigu)
        ->and($usulan['2024002']['alasan'])->toBe(AlasanAmbiguAdopsi::NikBerbeda)
        ->and($usulan['2024003']['status'])->toBe(StatusAdopsiFeeder::HanyaLokal)
        ->and($usulan['2024003']['kandidat'])->toBe([]);
});

it('mengenali mahasiswa yang sudah tertaut ke catatan lain', function () {
    $m = Mahasiswa::factory()->make(['feeder_id' => 'mhs-a', 'feeder_registrasi_id' => 'reg-a']);

    expect($this->pencocok->tertautLain($m, ['id_mahasiswa' => 'mhs-a', 'id_registrasi_mahasiswa' => 'reg-a']))->toBeFalse()
        ->and($this->pencocok->tertautLain($m, ['id_mahasiswa' => 'mhs-a', 'id_registrasi_mahasiswa' => 'reg-b']))->toBeTrue()
        ->and($this->pencocok->tertautLain(Mahasiswa::factory()->make(['feeder_id' => null, 'feeder_registrasi_id' => null]), kandidatUji('reg-c', null, null)))->toBeFalse();
});

it('meringkas perbandingan NIK tanpa menyimpannya', function () {
    expect($this->pencocok->nikSama('3201-0101-0101-0001', '3201010101010001'))->toBeTrue()
        ->and($this->pencocok->nikSama('3201010101010001', '3201010101010002'))->toBeFalse()
        ->and($this->pencocok->nikSama(null, '3201010101010002'))->toBeNull()
        ->and($this->pencocok->nikSama('3201010101010001', ''))->toBeNull();
});

it('membaca satu daftar adopsi dan mengubahnya menjadi kandidat', function () {
    config(['feeder.enabled' => true]);
    $fake = (new FakeFeederClient)->mahasiswaLampau([
        ['id_registrasi_mahasiswa' => 'r1', 'id_mahasiswa' => 'm1', 'nim' => ' 2019001 ', 'nama_mahasiswa' => 'Hana', 'nik' => '3201010101020001', 'tanggal_lahir' => '2001-01-11 00:00:00', 'id_prodi' => 'prodi-ti-0001', 'id_periode_masuk' => '20191'],
        ['id_mahasiswa' => 'm2', 'nim' => '2019002', 'nama_mahasiswa' => 'Tanpa Registrasi', 'id_prodi' => 'prodi-ti-0001', 'id_periode_masuk' => '20191'],
        ['id_registrasi_mahasiswa' => 'r3', 'id_mahasiswa' => 'm3', 'nim' => '2019003', 'nama_mahasiswa' => 'Prodi Lain', 'id_prodi' => 'prodi-lain', 'id_periode_masuk' => '20191'],
    ]);
    $this->app->instance(FeederClientInterface::class, $fake);
    $adopsi = app(FeederAdopsi::class);

    $rows = $adopsi->bacaDaftar('lampau', 'prodi-ti-0001', '20191');
    $kandidat = $adopsi->sebagaiKandidat($rows);

    expect($rows)->toHaveCount(2)
        ->and(array_keys($kandidat))->toBe(['r1'])
        ->and($kandidat['r1'])->toMatchArray(['nim' => '2019001', 'nik' => '3201010101020001', 'nama' => 'Hana', 'tanggal_lahir' => '2001-01-11']);
});

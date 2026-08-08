<?php

declare(strict_types=1);

use App\Models\Kemahasiswaan\Mahasiswa;
use App\Support\Demo;

/**
 * The demo install/remove pair.
 *
 * What is worth testing here is not that the commands run — it is that they
 * refuse. Both drop every table, so the guards are the feature and the seeding
 * is the easy part. Every case below stops the command *before* it reaches
 * migrate:fresh, which is also why they are safe to run inside the suite.
 */
it('menolak menghapus basis data yang tidak ditandai demo', function () {
    // Tanpa penanda, isi basis data ini ditaruh oleh seseorang — dan
    // menghapusnya berarti membuang data yang tidak pernah dibuat aplikasi ini.
    expect(Demo::terpasang())->toBeFalse();

    $this->artisan('openacademic:demo-hapus', ['--paksa' => true])
        ->expectsOutputToContain('tidak ditandai sebagai pasangan demo')
        ->assertFailed();
});

it('menolak memasang di atas data yang bukan demo', function () {
    Mahasiswa::factory()->create();

    $this->artisan('openacademic:demo-pasang')
        ->expectsOutputToContain('bukan pasangan demo')
        ->assertFailed();

    // Yang paling penting: penolakannya terjadi sebelum apa pun dihapus.
    expect(Mahasiswa::count())->toBe(1);
});

it('menandai basis data ketika data demo dipasang', function () {
    // Penanda ditulis oleh seeder, bukan oleh perintahnya, supaya setiap jalur
    // pemasangan ikut tertandai — termasuk migrate:fresh --seed.
    expect(Demo::terpasang())->toBeFalse();

    Demo::tandai();

    expect(Demo::terpasang())->toBeTrue()
        ->and(Demo::dipasangPada())->not->toBeNull();
});

it('meloloskan penghapusan setelah basis data ditandai', function () {
    // Pasangan dari tes pertama: penjaganya memang membedakan keduanya, bukan
    // sekadar selalu menolak.
    Demo::tandai();

    $this->artisan('openacademic:demo-hapus')
        ->expectsConfirmation('Lanjutkan?', 'no')
        ->assertSuccessful();

    expect(Demo::terpasang())->toBeTrue();
});

it('menolak kedua perintah pada APP_ENV=production', function () {
    // Data demo memakai kata sandi yang diketahui umum; tidak ada bendera yang
    // boleh membuka pintu ini.
    Demo::tandai();
    app()->instance('env', 'production');

    $this->artisan('openacademic:demo-pasang', ['--paksa' => true])->assertFailed();
    $this->artisan('openacademic:demo-hapus', ['--paksa' => true])->assertFailed();

    app()->instance('env', 'testing');
});

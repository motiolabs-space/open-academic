<?php

declare(strict_types=1);

use App\Models\System\Setting;
use App\Support\Instalasi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\ParallelTesting;
use Laravel\Passport\Passport;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    // Every feature test starts on a campus that has been set up. Without
    // this, a test that never creates an account — a public page, a PMB
    // applicant — is redirected to /setup (ADR-043). Tests of the wizard
    // itself call instalasiBaru().
    ->beforeEach(fn () => Instalasi::tandaiSelesai())
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeValidTermCode', function () {
    // PDDIKTI term encoding: YYYYS, S = 1 (Ganjil) | 2 (Genap) | 3 (Antara).
    expect((string) $this->value)->toMatch('/^\d{4}[123]$/');

    return $this;
});

/*
|--------------------------------------------------------------------------
| Kunci Passport, terpisah per proses uji
|--------------------------------------------------------------------------
*/

/**
 * Menyiapkan kunci OAuth pada direktori milik proses ini sendiri.
 *
 * `passport:keys` menulis ke `storage/oauth-*.key` — satu jalur yang dipakai
 * bersama oleh SELURUH proses saat suite berjalan paralel. Proses A yang
 * meregenerasi pasangan kunci di tengah jalan membuat token yang sudah
 * diterbitkan proses B tidak lagi terverifikasi, dan permintaan B pulang 401
 * alih-alih 200.
 *
 * Bukan teori: sebelum perbaikan ini, `pest tests/Feature/Sso --parallel
 * --processes=2` gagal pada 3 dari 5 percobaan, dengan pesan "Expected response
 * status code [200] but received 401". Serial selalu hijau justru karena tidak
 * pernah ada dua proses yang berebut berkasnya.
 *
 * Di luar mode paralel `ParallelTesting::token()` bernilai false, jadi jalurnya
 * tunggal dan perilakunya sama seperti sebelumnya.
 */
function siapkanKunciPassport(): void
{
    $token = ParallelTesting::token();

    $dir = storage_path('framework/testing/passport'.($token ? '-'.$token : ''));

    File::ensureDirectoryExists($dir);
    Passport::loadKeysFrom($dir);

    // Tanpa `--force`: sekali per proses sudah cukup, dan meregenerasi tiap tes
    // hanya menambah kerja yang tidak mengubah apa pun.
    if (!file_exists($dir.DIRECTORY_SEPARATOR.'oauth-private.key')) {
        Artisan::call('passport:keys', ['--quiet' => true]);
    }
}

/*
|--------------------------------------------------------------------------
| Isian wizard setup (ADR-043)
|--------------------------------------------------------------------------
|
| Di sini, bukan di berkas tesnya, karena dua berkas memakainya dan suite
| paralel tidak menjamin keduanya dimuat oleh proses yang sama.
|
*/

/** @param array<string, mixed> $ganti */
function isianSetup(array $ganti = []): array
{
    return array_merge([
        'nama_pt' => 'Institut Teknologi Lembah Hijau',
        'singkatan' => 'ITLH',
        'kode_pt' => '041234',
        'alamat' => 'Jl. Merbabu No. 7',
        'kota' => 'Salatiga',
        'provinsi' => 'Jawa Tengah',
        'surel' => 'Info@ITLH.ac.id',
        'telepon' => '0298 321 000',
        'situs' => 'https://itlh.ac.id',
        'warna_utama' => '#123456',
        'warna_aksen' => '#abcdef',
        'admin_nama' => 'Ratna Dewi',
        'admin_email' => 'ratna@itlh.ac.id',
        'admin_password' => 'rahasia-kampus-1',
        'admin_password_confirmation' => 'rahasia-kampus-1',
    ], $ganti);
}

/** Undoes the global beforeEach: a fresh installation, never set up. */
function instalasiBaru(): void
{
    // Deleted one by one so the Setting cache is busted with each row.
    Setting::query()->where('group', Instalasi::GRUP)->get()->each->delete();
}

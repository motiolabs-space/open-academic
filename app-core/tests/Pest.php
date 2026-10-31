<?php

declare(strict_types=1);

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
 * A JSON column read back from the database, compared without regard to key
 * order.
 *
 * MySQL's JSON type stores objects normalised: keys come back sorted by length
 * and then by byte, not in the order they were written. SQLite and PostgreSQL's
 * `json` keep the written order, so `toBe()` passed there and failed on MySQL
 * for the same row. Key order means nothing in a JSON object (and
 * FeederSyncLog::hashPayload() sorts keys before hashing), so both sides are
 * key-sorted here; values and their types are still compared strictly, and
 * lists keep their order.
 */
expect()->extend('toBeJsonColumn', function (array $expected) {
    $urutkan = function (array $larik) use (&$urutkan): array {
        if (!array_is_list($larik)) {
            ksort($larik);
        }

        return array_map(fn ($v) => is_array($v) ? $urutkan($v) : $v, $larik);
    };

    expect($this->value)->toBeArray();
    expect($urutkan($this->value))->toBe($urutkan($expected));

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

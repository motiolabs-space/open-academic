<?php

declare(strict_types=1);

use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;

/*
 * The README and docs/SSO.md both show `openacademic:sso-client --daftar` and
 * `--cabut=<id>` without a name, and until 23 October 2026 both invocations
 * died on "Not enough arguments (missing: nama)" — the name was a required
 * argument the listing branch never read. These tests pin the documented
 * invocations, not just the happy path of registering a client.
 */

beforeEach(function () {
    config(['sso.enabled' => true]);

    siapkanKunciPassport();
});

it('menampilkan daftar klien tanpa meminta nama', function () {
    app(ClientRepository::class)->createAuthorizationCodeGrantClient(
        name: 'Open Campus',
        redirectUris: ['https://campus.test/callback'],
        confidential: true,
    );

    $this->artisan('openacademic:sso-client', ['--daftar' => true])
        ->expectsOutputToContain('Open Campus')
        ->assertExitCode(0);
});

it('menolak pendaftaran tanpa nama aplikasi', function () {
    $this->artisan('openacademic:sso-client', [
        '--redirect' => ['https://campus.test/callback'],
    ])
        ->expectsOutputToContain('Nama aplikasi wajib diisi')
        ->assertExitCode(1);
});

it('mendaftarkan klien baru dengan nama dan redirect HTTPS', function () {
    $this->artisan('openacademic:sso-client', [
        'nama' => 'Open Campus',
        '--redirect' => ['https://campus.test/callback'],
    ])
        ->expectsOutputToContain('Aplikasi terdaftar')
        ->assertExitCode(0);

    expect(Passport::clientModel()::query()->where('name', 'Open Campus')->exists())
        ->toBeTrue();
});

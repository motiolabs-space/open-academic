<?php

declare(strict_types=1);

use App\Models\Kemahasiswaan\Mahasiswa;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;

/**
 * "Cabut" on the connected-apps screen has to stop the application for good.
 *
 * Revoking the access token alone left the refresh token alive, and Passport's
 * refresh grant never looks at the access token it came with: the application
 * traded the 30-day refresh token for a new access token and kept reading.
 */
beforeEach(function () {
    config(['sso.enabled' => true]);

    $this->seed(RolePermissionSeeder::class);

    Passport::tokensCan(config('sso.scopes'));
    Passport::setDefaultScope(...config('sso.default_scopes'));

    siapkanKunciPassport();

    $this->client = app(ClientRepository::class)->createAuthorizationCodeGrantClient(
        name: 'Open Campus',
        redirectUris: ['https://campus.test/callback'],
        confidential: true,
    );

    $this->mahasiswa = Mahasiswa::factory()->create();
    $this->mahasiswa->assignRole('mahasiswa');
});

/** @return array{access_token: string, refresh_token: string} */
function tokenSsoUntuk($test): array
{
    $test->actingAs($test->mahasiswa, 'mahasiswa')
        ->get('/oauth/authorize?'.http_build_query([
            'client_id' => $test->client->getKey(),
            'redirect_uri' => 'https://campus.test/callback',
            'response_type' => 'code',
            'scope' => 'identitas',
            'state' => 'uji-state',
        ]))
        ->assertOk();

    $lokasi = $test->actingAs($test->mahasiswa, 'mahasiswa')
        ->post('/oauth/authorize', [
            'state' => 'uji-state',
            'client_id' => $test->client->getKey(),
            'auth_token' => session('authToken'),
        ])
        ->headers->get('Location');

    parse_str(parse_url($lokasi, PHP_URL_QUERY) ?? '', $kueri);

    return $test->post('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $test->client->getKey(),
        'client_secret' => $test->client->plainSecret,
        'redirect_uri' => 'https://campus.test/callback',
        'code' => $kueri['code'],
    ])->assertOk()->json();
}

function segarkanToken($test, string $refreshToken)
{
    return $test->post('/oauth/token', [
        'grant_type' => 'refresh_token',
        'client_id' => $test->client->getKey(),
        'client_secret' => $test->client->plainSecret,
        'refresh_token' => $refreshToken,
        'scope' => 'identitas',
    ]);
}

it('mencabut refresh token bersama access token dari layar aplikasi terhubung', function () {
    $token = tokenSsoUntuk($this);

    $this->actingAs($this->mahasiswa, 'mahasiswa')
        ->delete(route('sso.aplikasi.cabut', $this->client->getKey()))
        ->assertRedirect();

    segarkanToken($this, $token['refresh_token'])->assertStatus(400);
});

it('mencabut refresh token saat klien dicabut dari baris perintah', function () {
    $token = tokenSsoUntuk($this);

    $this->artisan('openacademic:sso-client', ['--cabut' => $this->client->getKey()])
        ->assertExitCode(0);

    segarkanToken($this, $token['refresh_token'])->assertStatus(400);
});

it('tetap dapat menyegarkan token yang tidak dicabut', function () {
    $token = tokenSsoUntuk($this);

    segarkanToken($this, $token['refresh_token'])->assertOk();
});

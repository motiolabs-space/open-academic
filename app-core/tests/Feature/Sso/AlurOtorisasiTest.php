<?php

declare(strict_types=1);

use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Dosen;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;

beforeEach(function () {
    config(['sso.enabled' => true]);

    $this->seed(RolePermissionSeeder::class);

    Passport::tokensCan(config('sso.scopes'));
    Passport::setDefaultScope(...config('sso.default_scopes'));

    Artisan::call('passport:keys', ['--force' => true, '--quiet' => true]);

    $this->client = app(ClientRepository::class)->createAuthorizationCodeGrantClient(
        name: 'Open Campus',
        redirectUris: ['https://campus.test/callback'],
        confidential: true,
    );
});

function urlOtorisasi(string $clientId, string $scope = 'identitas'): string
{
    return '/oauth/authorize?'.http_build_query([
        'client_id' => $clientId,
        'redirect_uri' => 'https://campus.test/callback',
        'response_type' => 'code',
        'scope' => $scope,
        'state' => 'uji-state',
    ]);
}

it('mengarahkan tamu ke halaman masuk, bukan menampilkan persetujuan', function () {
    $this->get(urlOtorisasi($this->client->getKey()))
        ->assertRedirect(route('login'));
});

it('menampilkan layar persetujuan kepada mahasiswa yang sudah masuk', function () {
    $mahasiswa = Mahasiswa::factory()->create();
    $mahasiswa->assignRole('mahasiswa');

    $this->actingAs($mahasiswa, 'mahasiswa')
        ->get(urlOtorisasi($this->client->getKey()))
        ->assertOk()
        ->assertSee('Open Campus')
        ->assertSee($mahasiswa->nama)
        ->assertSee('Mengetahui nama, peran, dan nomor induk Anda');
});

it('tidak meminta masuk ulang untuk dosen yang sudah punya sesi', function () {
    $dosen = Dosen::factory()->create();
    $dosen->assignRole('dosen');

    // Inti dari SsoGuard: sesi guard "dosen" harus dikenali oleh Passport,
    // yang hanya tahu satu guard bernama "sso".
    $this->actingAs($dosen, 'dosen')
        ->get(urlOtorisasi($this->client->getKey()))
        ->assertOk()
        ->assertSee($dosen->nama);
});

it('memakai UUID sebagai subject, bukan id yang bertabrakan antar tabel', function () {
    $mahasiswa = Mahasiswa::factory()->create();
    $mahasiswa->assignRole('mahasiswa');

    $this->actingAs($mahasiswa, 'mahasiswa')
        ->get(urlOtorisasi($this->client->getKey()))
        ->assertOk();

    $auth = unserialize(session('authRequest'));

    expect($auth->getUser()->getIdentifier())
        ->toBe($mahasiswa->uuid)
        ->not->toBe((string) $mahasiswa->id);
});

it('menolak peran yang tidak diizinkan kampus', function () {
    config(['sso.allowed_roles' => ['mahasiswa']]);

    $dosen = Dosen::factory()->create();
    $dosen->assignRole('dosen');

    $this->actingAs($dosen, 'dosen')
        ->get(urlOtorisasi($this->client->getKey()))
        ->assertForbidden();
});

it('menolak scope yang tidak terdaftar', function () {
    $mahasiswa = Mahasiswa::factory()->create();
    $mahasiswa->assignRole('mahasiswa');

    $this->actingAs($mahasiswa, 'mahasiswa')
        ->get(urlOtorisasi($this->client->getKey(), 'nilai.hapus'))
        ->assertRedirectContains('error=invalid_scope');
});

it('menerbitkan access token yang dapat ditukar dari kode otorisasi', function () {
    $mahasiswa = Mahasiswa::factory()->create();
    $mahasiswa->assignRole('mahasiswa');

    $this->actingAs($mahasiswa, 'mahasiswa')
        ->get(urlOtorisasi($this->client->getKey()))
        ->assertOk();

    $lokasi = $this->actingAs($mahasiswa, 'mahasiswa')
        ->post('/oauth/authorize', [
            'state' => 'uji-state',
            'client_id' => $this->client->getKey(),
            'auth_token' => session('authToken'),
        ])
        ->assertRedirect()
        ->headers->get('Location');

    parse_str(parse_url($lokasi, PHP_URL_QUERY) ?? '', $kueri);

    expect($kueri)->toHaveKey('code');

    $token = $this->post('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $this->client->getKey(),
        'client_secret' => $this->client->plainSecret,
        'redirect_uri' => 'https://campus.test/callback',
        'code' => $kueri['code'],
    ])->assertOk()->json();

    expect($token)->toHaveKeys(['access_token', 'refresh_token', 'expires_in']);
});

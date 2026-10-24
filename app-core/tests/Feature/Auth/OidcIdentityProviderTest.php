<?php

declare(strict_types=1);

use App\Services\Auth\Idp\IdpException;
use App\Services\Auth\Idp\OidcIdentityProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * Driver OIDC sungguhan, melawan HTTP palsu.
 *
 * Satu-satunya kode di fitur ini yang menyentuh jaringan, jadi yang dipaku di
 * sini adalah bentuk percakapannya: discovery dibaca dari issuer, kode ditukar
 * dengan client secret lewat POST, dan klaim id_token melewati validator yang
 * sama dengan penyedia palsu.
 */
beforeEach(function () {
    config([
        'idp.driver' => 'oidc',
        'idp.issuer' => 'https://idp.uji.test',
        'idp.client_id' => 'open-academic-uji',
        'idp.client_secret' => 'rahasia-klien',
    ]);

    $this->driver = app(OidcIdentityProvider::class);
});

/** JWS kompak tanpa tanda tangan yang berarti — cukup untuk dibaca muatannya. */
function idTokenPalsu(array $claims): string
{
    $enc = fn (array $bagian) => rtrim(strtr(base64_encode(json_encode($bagian)), '+/', '-_'), '=');

    return $enc(['alg' => 'RS256', 'kid' => 'uji']).'.'.$enc($claims).'.tandatangan';
}

function palsukanIdp(array $claims, string $issuerDiDiscovery = 'https://idp.uji.test'): void
{
    Http::fake([
        'https://idp.uji.test/.well-known/openid-configuration' => Http::response([
            'issuer' => $issuerDiDiscovery,
            'authorization_endpoint' => 'https://idp.uji.test/protocol/openid-connect/auth',
            'token_endpoint' => 'https://idp.uji.test/protocol/openid-connect/token',
        ]),
        'https://idp.uji.test/protocol/openid-connect/token' => Http::response([
            'access_token' => 'akses-uji',
            'token_type' => 'Bearer',
            'id_token' => idTokenPalsu($claims),
        ]),
    ]);
}

it('menyusun URL otorisasi dari dokumen discovery', function () {
    palsukanIdp([]);

    $url = $this->driver->authorizationUrl('state-1', 'nonce-1', 'https://siakad.test/masuk/idp/callback');

    expect($url)->toStartWith('https://idp.uji.test/protocol/openid-connect/auth?')
        ->toContain('response_type=code')
        ->toContain('scope=openid+email+profile')
        ->toContain('state=state-1')
        ->toContain('nonce=nonce-1')
        ->toContain('client_id=open-academic-uji');
});

it('menukar kode dengan client secret dan membaca identitas dari id_token', function () {
    palsukanIdp([
        'iss' => 'https://idp.uji.test',
        'aud' => 'open-academic-uji',
        'exp' => now()->addMinutes(5)->timestamp,
        'nonce' => 'nonce-1',
        'sub' => 'f81d4fae',
        'email' => 'Staf@Kampus.test',
        'email_verified' => true,
        'name' => 'Staf Uji',
    ]);

    $identitas = $this->driver->exchange('kode-1', 'nonce-1', 'https://siakad.test/masuk/idp/callback');

    expect($identitas->subject)->toBe('f81d4fae')
        ->and($identitas->email)->toBe('staf@kampus.test')
        ->and($identitas->emailVerified)->toBeTrue()
        ->and($identitas->name)->toBe('Staf Uji')
        ->and($identitas->issuer)->toBe('https://idp.uji.test');

    Http::assertSent(fn (Request $r) => $r->url() === 'https://idp.uji.test/protocol/openid-connect/token'
        && $r->method() === 'POST'
        && $r['grant_type'] === 'authorization_code'
        && $r['code'] === 'kode-1'
        && $r['client_secret'] === 'rahasia-klien'
        && $r['redirect_uri'] === 'https://siakad.test/masuk/idp/callback');
});

it('menolak id_token yang klaimnya salah, lewat validator yang sama dengan penyedia palsu', function () {
    palsukanIdp([
        'iss' => 'https://idp.uji.test',
        'aud' => 'aplikasi-lain',
        'exp' => now()->addMinutes(5)->timestamp,
        'nonce' => 'nonce-1',
        'sub' => 'f81d4fae',
    ]);

    expect(fn () => $this->driver->exchange('kode-1', 'nonce-1', 'https://siakad.test/cb'))
        ->toThrow(fn (IdpException $e) => expect($e->alasan)->toBe('aud_salah'));
});

it('menolak dokumen discovery yang menyebut penerbit lain', function () {
    palsukanIdp([], issuerDiDiscovery: 'https://penyusup.test');

    expect(fn () => $this->driver->authorizationUrl('s', 'n', 'https://siakad.test/cb'))
        ->toThrow(fn (IdpException $e) => expect($e->alasan)->toBe('discovery_gagal'));
});

it('menolak issuer tanpa TLS sebelum menyentuh jaringan', function () {
    Http::fake();

    config(['idp.issuer' => 'http://idp.uji.test']);

    expect(fn () => $this->driver->authorizationUrl('s', 'n', 'https://siakad.test/cb'))
        ->toThrow(fn (IdpException $e) => expect($e->alasan)->toBe('issuer_tanpa_tls'));

    Http::assertNothingSent();
});

it('menyampaikan kegagalan token endpoint tanpa isi responsnya', function () {
    Http::fake([
        'https://idp.uji.test/.well-known/openid-configuration' => Http::response([
            'issuer' => 'https://idp.uji.test',
            'authorization_endpoint' => 'https://idp.uji.test/auth',
            'token_endpoint' => 'https://idp.uji.test/token',
        ]),
        'https://idp.uji.test/token' => Http::response(['error' => 'invalid_grant', 'echo' => 'kode-1'], 400),
    ]);

    try {
        $this->driver->exchange('kode-1', 'nonce-1', 'https://siakad.test/cb');
        $this->fail('Expected IdpException.');
    } catch (IdpException $e) {
        expect($e->alasan)->toBe('tukar_kode_gagal')
            ->and($e->getMessage())->toContain('400')
            ->not->toContain('kode-1');
    }
});

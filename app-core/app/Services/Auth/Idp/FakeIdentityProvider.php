<?php

declare(strict_types=1);

namespace App\Services\Auth\Idp;

use App\DTOs\Auth\IdentitasEksternal;
use App\Services\Auth\Idp\Contracts\IdentityProviderInterface;

/**
 * An identity provider that never touches the network.
 *
 * Tests (and a demo installation) register the claims a code should resolve
 * to; the fake then runs the same claim validation as the real driver, so a
 * wrong issuer or a stale nonce is refused here exactly as it would be there.
 *
 * Bound as a singleton so what a test registers is what the controller sees.
 */
final class FakeIdentityProvider implements IdentityProviderInterface
{
    /** @var array<string, array<string, mixed>> code => claims */
    private array $kode = [];

    public function __construct(private readonly ValidatorKlaim $validator) {}

    /**
     * Makes `$code` resolve to these claims on the next exchange.
     *
     * `iss`, `aud`, `exp` and `nonce` default to values that pass, so a test
     * states only what it is about. `nonce` is normally left for exchange() to
     * fill from the real session value; pass one explicitly to test a mismatch.
     *
     * @param array<string, mixed> $claims
     */
    public function akanMengembalikan(string $code, array $claims): void
    {
        $this->kode[$code] = $claims;
    }

    public function authorizationUrl(string $state, string $nonce, string $redirectUri): string
    {
        return rtrim((string) config('idp.issuer'), '/').'/authorize?'.http_build_query([
            'client_id' => config('idp.client_id'),
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => implode(' ', config('idp.scopes')),
            'state' => $state,
            'nonce' => $nonce,
        ]);
    }

    public function exchange(string $code, string $nonce, string $redirectUri): IdentitasEksternal
    {
        if (!isset($this->kode[$code])) {
            throw new IdpException('kode_ditolak', 'Authorization code was not issued by the provider.');
        }

        // Single use, like a real code.
        $claims = $this->kode[$code];
        unset($this->kode[$code]);

        $claims += [
            'iss' => (string) config('idp.issuer'),
            'aud' => (string) config('idp.client_id'),
            'exp' => now()->addMinutes(5)->timestamp,
            'nonce' => $nonce,
        ];

        return $this->validator->validasi(
            $claims,
            (string) config('idp.issuer'),
            (string) config('idp.client_id'),
            $nonce,
        );
    }
}

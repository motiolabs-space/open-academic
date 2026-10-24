<?php

declare(strict_types=1);

namespace App\Services\Auth\Idp\Contracts;

use App\DTOs\Auth\IdentitasEksternal;
use App\Services\Auth\Idp\IdpException;

/**
 * An upstream identity provider Open Academic delegates sign-in to.
 *
 * The two legs of OIDC authorization-code, and nothing else. State and nonce
 * are generated and remembered by the caller; the provider only has to carry
 * them out and, on the way back, prove the nonce came home inside the token.
 */
interface IdentityProviderInterface
{
    /** Where to send the browser to sign in. */
    public function authorizationUrl(string $state, string $nonce, string $redirectUri): string;

    /**
     * Exchanges the code for an identity, verifying `iss`, `aud`, `exp` and
     * `nonce` on the ID token before anything is read from it.
     *
     * @throws IdpException on any failure, with no token material in it
     */
    public function exchange(string $code, string $nonce, string $redirectUri): IdentitasEksternal;
}

<?php

declare(strict_types=1);

namespace App\Services\Auth\Idp;

use App\DTOs\Auth\IdentitasEksternal;

/**
 * The ID-token checks every driver runs, real or fake.
 *
 * OIDC Core §3.1.3.7, the parts a confidential client that received the token
 * straight from the token endpoint over TLS must still do itself: issuer,
 * audience (and `azp` when there is more than one), expiry, and the nonce that
 * ties this token to the redirect this browser started.
 *
 * Shared on purpose. A fake that skipped these would let the test suite pass
 * on a flow the real driver refuses, which is the opposite of what a fake is
 * for.
 */
final class ValidatorKlaim
{
    /** Tolerance for clock skew between this server and the IdP, in seconds. */
    private const TOLERANSI_DETIK = 60;

    /**
     * @param array<string, mixed> $claims
     *
     * @throws IdpException
     */
    public function validasi(array $claims, string $issuer, string $clientId, string $nonce): IdentitasEksternal
    {
        if ($issuer === '' || ($claims['iss'] ?? null) !== $issuer) {
            throw new IdpException('iss_salah', 'ID token issued by a different issuer than configured.');
        }

        $aud = $claims['aud'] ?? null;
        $audiens = is_array($aud) ? $aud : [$aud];

        if ($clientId === '' || !in_array($clientId, $audiens, true)) {
            throw new IdpException('aud_salah', 'ID token is not addressed to this client.');
        }

        if (count($audiens) > 1 && ($claims['azp'] ?? null) !== $clientId) {
            throw new IdpException('azp_salah', 'ID token names several audiences without authorising this client.');
        }

        $exp = $claims['exp'] ?? null;

        if (!is_numeric($exp) || (int) $exp + self::TOLERANSI_DETIK < now()->timestamp) {
            throw new IdpException('kedaluwarsa', 'ID token has expired.');
        }

        if ($nonce === '' || !hash_equals($nonce, (string) ($claims['nonce'] ?? ''))) {
            throw new IdpException('nonce_salah', 'ID token nonce does not match this sign-in attempt.');
        }

        if (!isset($claims['sub']) || !is_scalar($claims['sub']) || (string) $claims['sub'] === '') {
            throw new IdpException('tanpa_subject', 'ID token carries no subject.');
        }

        return IdentitasEksternal::fromClaims($claims);
    }
}

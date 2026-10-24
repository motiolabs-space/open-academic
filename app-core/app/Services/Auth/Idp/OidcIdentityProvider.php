<?php

declare(strict_types=1);

namespace App\Services\Auth\Idp;

use App\DTOs\Auth\IdentitasEksternal;
use App\Services\Auth\Idp\Contracts\IdentityProviderInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * A minimal OpenID Connect relying party: discovery, authorization-code,
 * ID-token claims. Enough for Google Workspace, Microsoft Entra ID and
 * Keycloak, all of which publish a discovery document at the issuer.
 *
 * Why not a client library (ADR-019): the application needs exactly two HTTP
 * calls and a claims check, and every library that generalises those also
 * brings a provider catalogue, a session abstraction and a dependency tree
 * that nobody here would read. What is below can be audited in one sitting.
 *
 * The ID token's signature is not verified against the JWKS. The token is
 * received by this server directly from the token endpoint over TLS, with the
 * client secret, which is the case OIDC Core §3.1.3.7.6 allows TLS server
 * validation to stand in for. That is why `issuer` must be https and why this
 * class refuses to run against anything else. A campus that wants signature
 * verification on top binds its own IdentityProviderInterface.
 */
final class OidcIdentityProvider implements IdentityProviderInterface
{
    /** How long a discovery document is reused, in seconds. */
    private const UMUR_DISCOVERY = 3600;

    public function __construct(private readonly ValidatorKlaim $validator) {}

    public function authorizationUrl(string $state, string $nonce, string $redirectUri): string
    {
        $meta = $this->discovery();

        return $meta['authorization_endpoint'].'?'.http_build_query([
            'client_id' => (string) config('idp.client_id'),
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => implode(' ', config('idp.scopes')),
            'state' => $state,
            'nonce' => $nonce,
        ]);
    }

    public function exchange(string $code, string $nonce, string $redirectUri): IdentitasEksternal
    {
        $meta = $this->discovery();

        try {
            $response = Http::asForm()
                ->timeout((int) config('idp.timeout'))
                ->acceptJson()
                ->post($meta['token_endpoint'], [
                    'grant_type' => 'authorization_code',
                    'code' => $code,
                    'redirect_uri' => $redirectUri,
                    'client_id' => (string) config('idp.client_id'),
                    'client_secret' => (string) config('idp.client_secret'),
                ]);
        } catch (ConnectionException) {
            throw new IdpException('idp_tidak_terjangkau', 'Token endpoint could not be reached.');
        }

        if ($response->failed()) {
            // The status is enough to diagnose; the body may echo the code.
            throw new IdpException('tukar_kode_gagal', 'Token endpoint answered HTTP '.$response->status().'.');
        }

        $idToken = $response->json('id_token');

        if (!is_string($idToken) || $idToken === '') {
            throw new IdpException('tanpa_id_token', 'Token response carried no id_token; is the openid scope requested?');
        }

        return $this->validator->validasi(
            $this->klaimDari($idToken),
            $this->issuer(),
            (string) config('idp.client_id'),
            $nonce,
        );
    }

    /**
     * @return array<string, mixed>
     *
     * @throws IdpException
     */
    private function discovery(): array
    {
        $issuer = $this->issuer();

        return Cache::remember('idp.discovery.'.sha1($issuer), self::UMUR_DISCOVERY, function () use ($issuer): array {
            try {
                $response = Http::timeout((int) config('idp.timeout'))
                    ->acceptJson()
                    ->get($issuer.'/.well-known/openid-configuration');
            } catch (ConnectionException) {
                throw new IdpException('discovery_gagal', 'Discovery document could not be fetched.');
            }

            $meta = $response->json();

            if ($response->failed() || !is_array($meta)) {
                throw new IdpException('discovery_gagal', 'Discovery document answered HTTP '.$response->status().'.');
            }

            foreach (['authorization_endpoint', 'token_endpoint'] as $wajib) {
                if (!isset($meta[$wajib]) || !is_string($meta[$wajib])) {
                    throw new IdpException('discovery_gagal', "Discovery document has no {$wajib}.");
                }
            }

            // OpenID Connect Discovery §4.3: the document must name the issuer
            // it was fetched from, or it is somebody else's document.
            if (rtrim((string) ($meta['issuer'] ?? ''), '/') !== $issuer) {
                throw new IdpException('discovery_gagal', 'Discovery document names a different issuer.');
            }

            return $meta;
        });
    }

    /** @throws IdpException */
    private function issuer(): string
    {
        $issuer = rtrim((string) config('idp.issuer'), '/');

        if (!str_starts_with($issuer, 'https://')) {
            throw new IdpException('issuer_tanpa_tls', 'IDP_ISSUER must be an https URL; TLS is what authenticates the token endpoint.');
        }

        return $issuer;
    }

    /**
     * The payload of a compact JWS, without checking its signature — see the
     * class comment for why that is acceptable here.
     *
     * @return array<string, mixed>
     *
     * @throws IdpException
     */
    private function klaimDari(string $jwt): array
    {
        $bagian = explode('.', $jwt);

        if (count($bagian) !== 3) {
            throw new IdpException('id_token_rusak', 'id_token is not a compact JWS.');
        }

        $json = base64_decode(strtr($bagian[1], '-_', '+/'), true);
        $claims = is_string($json) ? json_decode($json, true) : null;

        if (!is_array($claims)) {
            throw new IdpException('id_token_rusak', 'id_token payload is not valid JSON.');
        }

        return $claims;
    }
}

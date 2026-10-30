<?php

declare(strict_types=1);

namespace App\Models\Sso;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Laravel\Passport\Client;

/**
 * Passport's client, taught which applications are the campus's own.
 *
 * Passport never skips the consent screen by itself. SSO_FIRST_PARTY was
 * documented as doing so but nothing read it at authorisation time; this is
 * where both that list and the per-client flag set on the SSO screen are
 * finally honoured.
 */
class KlienOAuth extends Client
{
    public function pengaturan(): HasOne
    {
        return $this->hasOne(KlienSso::class, 'oauth_client_id');
    }

    public function pihakPertama(): bool
    {
        return in_array((string) $this->getKey(), (array) config('sso.first_party'), true)
            || (bool) $this->pengaturan?->is_first_party;
    }

    public function skipsAuthorization(Authenticatable $user, array $scopes): bool
    {
        return !$this->revoked && $this->pihakPertama();
    }
}

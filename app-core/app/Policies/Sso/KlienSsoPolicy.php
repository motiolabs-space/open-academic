<?php

declare(strict_types=1);

namespace App\Policies\Sso;

use App\Policies\Concerns\ResolvesActor;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * SSO clients: the same trust as Campus Bridge credentials, so the same
 * permission. A client secret lets an application turn a student's consent
 * into a token, and a first-party flag removes the consent screen entirely.
 */
class KlienSsoPolicy
{
    use ResolvesActor;

    public function kelola(Authenticatable $actor): bool
    {
        return $this->stafDenganIzin($actor, 'integrasi.kredensial');
    }
}

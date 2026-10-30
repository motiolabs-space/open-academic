<?php

declare(strict_types=1);

namespace App\Policies\Bridge;

use App\Models\Bridge\BridgeConsumer;
use App\Policies\Concerns\ResolvesActor;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Reading the Bridge console, and holding the keys to it.
 *
 * Two different trusts. `bridge.view` shows which applications are connected
 * and whether deliveries arrive. `integrasi.kredensial` creates consumers and
 * issues, revokes and rotates what they authenticate with — the same
 * permission as the SSO client screen, held by the super-admin and the IT
 * office (`tik`) and nobody else by default: a token is a standing grant to
 * read student records in bulk.
 */
class BridgeConsumerPolicy
{
    use ResolvesActor;

    public function viewAny(Authenticatable $actor): bool
    {
        return $this->stafDenganIzin($actor, 'bridge.view');
    }

    public function view(Authenticatable $actor, BridgeConsumer $konsumen): bool
    {
        return $this->stafDenganIzin($actor, 'bridge.view');
    }

    public function kelolaKredensial(Authenticatable $actor): bool
    {
        return $this->stafDenganIzin($actor, 'integrasi.kredensial');
    }
}

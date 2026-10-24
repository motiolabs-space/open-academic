<?php

declare(strict_types=1);

namespace App\Policies\Feeder;

use App\Models\Feeder\FeederChangeRequest;
use App\Policies\Concerns\ResolvesActor;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Who may see and process a PDM/PJK request.
 *
 * The same two permissions as the rest of the reporting console: reading
 * the list is `feeder.view`; filing, sending, and declaring a request done
 * at PDDIKTI is `feeder.sync`, because each of those is a statement about
 * what the national database now holds.
 */
class FeederChangeRequestPolicy
{
    use ResolvesActor;

    public function viewAny(Authenticatable $actor): bool
    {
        return $this->stafDenganIzin($actor, 'feeder.view');
    }

    public function view(Authenticatable $actor, FeederChangeRequest $perubahan): bool
    {
        return $this->stafDenganIzin($actor, 'feeder.view');
    }

    public function update(Authenticatable $actor, FeederChangeRequest $perubahan): bool
    {
        return $this->stafDenganIzin($actor, 'feeder.sync');
    }
}

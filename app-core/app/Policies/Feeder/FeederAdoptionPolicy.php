<?php

declare(strict_types=1);

namespace App\Policies\Feeder;

use App\Models\Feeder\FeederAdoption;
use App\Policies\Concerns\ResolvesActor;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Who may read the adoption report and decide its ambiguous rows.
 *
 * Reading is `feeder.view`. Running adoption and linking or separating a
 * student is `feeder.sync`: each decides which PDDIKTI record a student's
 * grades and credits will be reported under from then on.
 */
class FeederAdoptionPolicy
{
    use ResolvesActor;

    public function viewAny(Authenticatable $actor): bool
    {
        return $this->stafDenganIzin($actor, 'feeder.view');
    }

    public function create(Authenticatable $actor): bool
    {
        return $this->stafDenganIzin($actor, 'feeder.sync');
    }

    public function update(Authenticatable $actor, FeederAdoption $adopsi): bool
    {
        return $this->stafDenganIzin($actor, 'feeder.sync');
    }
}

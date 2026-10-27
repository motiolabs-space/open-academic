<?php

declare(strict_types=1);

namespace App\Policies\Feeder;

use App\Policies\Concerns\ResolvesActor;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Who may read PDDIKTI's past-student list and claim from it.
 *
 * Reading the stored report is `feeder.view`. Reading PDDIKTI again and
 * claiming are `feeder.sync`: a claim changes what the campus's Feeder holds,
 * and is followed by an adoption run that links students to records.
 */
class FeederPastStudentPolicy
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

    public function klaim(Authenticatable $actor): bool
    {
        return $this->stafDenganIzin($actor, 'feeder.sync');
    }
}

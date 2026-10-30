<?php

declare(strict_types=1);

namespace App\Policies\Feeder;

use App\Policies\Concerns\ResolvesActor;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Who may read the dictionary report and who may ask Feeder again.
 *
 * Reading is `feeder.view`. A new reading is `feeder.sync`: it changes
 * nothing at PDDIKTI, but it is one web-service call per act against the
 * campus's Feeder, and the operators who run syncs are the ones who know
 * when Feeder was upgraded.
 */
class FeederDictionaryRunPolicy
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
}

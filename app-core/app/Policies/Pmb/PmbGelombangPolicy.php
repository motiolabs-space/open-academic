<?php

declare(strict_types=1);

namespace App\Policies\Pmb;

use App\Models\Pmb\PmbGelombang;
use App\Policies\Concerns\ResolvesActor;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * A wave's settings, schedule, score import and announcement: the admissions
 * desk's business alone.
 */
class PmbGelombangPolicy
{
    use ResolvesActor;

    public function view(Authenticatable $actor, PmbGelombang $gelombang): bool
    {
        return $this->stafDenganIzin($actor, 'pmb.view');
    }

    public function update(Authenticatable $actor, PmbGelombang $gelombang): bool
    {
        return $this->stafDenganIzin($actor, 'pmb.manage');
    }
}

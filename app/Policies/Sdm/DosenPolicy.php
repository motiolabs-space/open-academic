<?php

declare(strict_types=1);

namespace App\Policies\Sdm;

use App\Models\Sdm\Dosen;
use App\Policies\Concerns\ResolvesActor;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Lecturer record authorisation. Personnel data (NIP, employment status,
 * teaching load) is staff territory; a lecturer may only maintain their own row.
 */
class DosenPolicy
{
    use ResolvesActor;

    public function viewAny(Authenticatable $actor): bool
    {
        return $this->stafDenganIzin($actor, 'dosen.view');
    }

    public function view(Authenticatable $actor, Dosen $dosen): bool
    {
        if ($this->dosen($actor)) {
            return $actor->getKey() === $dosen->getKey();
        }

        return $this->stafDenganIzin($actor, 'dosen.view');
    }

    public function create(Authenticatable $actor): bool
    {
        return $this->stafDenganIzin($actor, 'dosen.manage');
    }

    public function update(Authenticatable $actor, Dosen $dosen): bool
    {
        if ($this->dosen($actor)) {
            return $actor->getKey() === $dosen->getKey();
        }

        return $this->stafDenganIzin($actor, 'dosen.manage');
    }

    public function delete(Authenticatable $actor, Dosen $dosen): bool
    {
        return $this->stafDenganIzin($actor, 'dosen.manage');
    }
}

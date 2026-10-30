<?php

declare(strict_types=1);

namespace App\Policies\Akademik;

use App\Models\Akademik\AkreditasiProdi;
use App\Policies\Concerns\ResolvesActor;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * A programme's accreditation history: master data, so read with
 * master.view and written with master.manage. Lecturers and students never
 * reach it; the grade they see is printed elsewhere.
 */
class AkreditasiProdiPolicy
{
    use ResolvesActor;

    public function viewAny(Authenticatable $actor): bool
    {
        return $this->stafDenganIzin($actor, 'master.view');
    }

    public function view(Authenticatable $actor, AkreditasiProdi $akreditasi): bool
    {
        return $this->stafDenganIzin($actor, 'master.view');
    }

    public function create(Authenticatable $actor): bool
    {
        return $this->stafDenganIzin($actor, 'master.manage');
    }

    public function delete(Authenticatable $actor, AkreditasiProdi $akreditasi): bool
    {
        return $this->stafDenganIzin($actor, 'master.manage');
    }
}

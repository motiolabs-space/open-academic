<?php

declare(strict_types=1);

namespace App\Policies\Kemahasiswaan;

use App\Models\Kemahasiswaan\CutiMahasiswa;
use App\Policies\Concerns\ResolvesActor;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Leave applications.
 *
 * A student files for themselves only — the route takes no student — and reads
 * only their own. Deciding stays with the registrar.
 */
class CutiMahasiswaPolicy
{
    use ResolvesActor;

    public function create(Authenticatable $actor): bool
    {
        return $this->mahasiswa($actor) && $this->izin($actor, 'cuti.ajukan');
    }

    public function view(Authenticatable $actor, CutiMahasiswa $cuti): bool
    {
        if ($this->mahasiswa($actor)) {
            return $actor->getKey() === $cuti->mahasiswa_id;
        }

        return $this->stafDenganIzin($actor, 'mahasiswa.view');
    }
}

<?php

declare(strict_types=1);

namespace App\Policies\Akademik;

use App\Models\Akademik\KelasKuliah;
use App\Policies\Concerns\ResolvesActor;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Who may read stored outcome attainment, portal by portal.
 *
 *  - staff with nilai.view: the programme recap, any class;
 *  - a lecturer: the classes they teach, nothing else — the same stance as
 *    the class analytics and the EDOM results;
 *  - a student: their own, and only through the route that takes no
 *    identifier at all.
 */
class CapaianCplMahasiswaPolicy
{
    use ResolvesActor;

    /** The programme × cohort recap. */
    public function viewAny(Authenticatable $actor): bool
    {
        return $this->stafDenganIzin($actor, 'nilai.view');
    }

    public function viewKelas(Authenticatable $actor, KelasKuliah $kelas): bool
    {
        if ($this->stafDenganIzin($actor, 'nilai.view')) {
            return true;
        }

        return $this->dosen($actor)
            && $this->izin($actor, 'nilai.view')
            && $kelas->dosen()->where('dosen.id', $actor->getKey())->exists();
    }

    public function viewOwn(Authenticatable $actor): bool
    {
        return $this->mahasiswa($actor);
    }
}

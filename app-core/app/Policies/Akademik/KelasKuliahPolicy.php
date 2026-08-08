<?php

declare(strict_types=1);

namespace App\Policies\Akademik;

use App\Models\Akademik\KelasKuliah;
use App\Policies\Concerns\ResolvesActor;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Class offering authorisation.
 *
 * Reading a class is broad — students browse the catalogue when filling a KRS.
 * Writing to one, including recording attendance, is limited to the lecturers
 * actually assigned to it.
 */
class KelasKuliahPolicy
{
    use ResolvesActor;

    public function viewAny(Authenticatable $actor): bool
    {
        return $this->peran($actor) !== null;
    }

    public function view(Authenticatable $actor, KelasKuliah $kelas): bool
    {
        return $this->peran($actor) !== null;
    }

    public function create(Authenticatable $actor): bool
    {
        return $this->stafDenganIzin($actor, 'kelas.manage');
    }

    public function update(Authenticatable $actor, KelasKuliah $kelas): bool
    {
        return $this->stafDenganIzin($actor, 'kelas.manage');
    }

    public function delete(Authenticatable $actor, KelasKuliah $kelas): bool
    {
        return $this->stafDenganIzin($actor, 'kelas.manage');
    }

    /** Entering grades for this class. */
    public function grade(Authenticatable $actor, KelasKuliah $kelas): bool
    {
        return $this->dosen($actor)
            && $this->izin($actor, 'nilai.manage')
            && $this->mengampu($actor->getKey(), $kelas);
    }

    /** Recording attendance for this class. */
    public function attend(Authenticatable $actor, KelasKuliah $kelas): bool
    {
        return $this->dosen($actor)
            && $this->izin($actor, 'presensi.manage')
            && $this->mengampu($actor->getKey(), $kelas);
    }

    private function mengampu(int $dosenId, KelasKuliah $kelas): bool
    {
        return $kelas->dosen()->where('dosen.id', $dosenId)->exists();
    }
}

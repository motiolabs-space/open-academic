<?php

declare(strict_types=1);

namespace App\Policies\Kemahasiswaan;

use App\Models\Kemahasiswaan\Mahasiswa;
use App\Policies\Concerns\ResolvesActor;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Who may read and edit a student record.
 *
 * The rule that matters: a student sees exactly one record — their own. A
 * lecturer sees the students they advise or teach. Everything wider requires
 * an explicit staff permission.
 */
class MahasiswaPolicy
{
    use ResolvesActor;

    public function viewAny(Authenticatable $actor): bool
    {
        return $this->stafDenganIzin($actor, 'mahasiswa.view')
            || $this->izin($actor, 'bimbingan.view');
    }

    public function view(Authenticatable $actor, Mahasiswa $mahasiswa): bool
    {
        if ($this->mahasiswa($actor)) {
            return $actor->getKey() === $mahasiswa->getKey();
        }

        if ($this->dosen($actor)) {
            return $mahasiswa->dosen_wali_id === $actor->getKey()
                || $this->mengajarMahasiswa($actor->getKey(), $mahasiswa);
        }

        return $this->stafDenganIzin($actor, 'mahasiswa.view');
    }

    public function create(Authenticatable $actor): bool
    {
        return $this->stafDenganIzin($actor, 'mahasiswa.manage');
    }

    public function update(Authenticatable $actor, Mahasiswa $mahasiswa): bool
    {
        // A student may maintain their own contact details; academic fields
        // are filtered by the FormRequest, not by this policy.
        if ($this->mahasiswa($actor)) {
            return $actor->getKey() === $mahasiswa->getKey()
                && $this->izin($actor, 'profil.manage');
        }

        return $this->stafDenganIzin($actor, 'mahasiswa.manage');
    }

    public function delete(Authenticatable $actor, Mahasiswa $mahasiswa): bool
    {
        return $this->stafDenganIzin($actor, 'mahasiswa.manage');
    }

    /** Whether the lecturer teaches a class this student is enrolled in. */
    private function mengajarMahasiswa(int $dosenId, Mahasiswa $mahasiswa): bool
    {
        return $mahasiswa->krs()
            ->whereHas(
                'detail.kelasKuliah.dosen',
                fn ($query) => $query->where('dosen.id', $dosenId),
            )
            ->exists();
    }
}

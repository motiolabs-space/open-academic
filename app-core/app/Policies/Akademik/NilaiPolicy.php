<?php

declare(strict_types=1);

namespace App\Policies\Akademik;

use App\Models\Akademik\Nilai;
use App\Policies\Concerns\ResolvesActor;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Grade authorisation.
 *
 * A finalised grade is closed to everyone, including the lecturer who entered
 * it. Correcting one goes through the audited correction path, which is a
 * separate ability with a narrower audience.
 */
class NilaiPolicy
{
    use ResolvesActor;

    public function viewAny(Authenticatable $actor): bool
    {
        return $this->mahasiswa($actor)
            || $this->izin($actor, 'nilai.view')
            || $this->stafDenganIzin($actor, 'nilai.view');
    }

    public function view(Authenticatable $actor, Nilai $nilai): bool
    {
        if ($this->mahasiswa($actor)) {
            return $actor->getKey() === $nilai->mahasiswa_id;
        }

        if ($this->dosen($actor)) {
            return $this->mengampu($actor->getKey(), $nilai);
        }

        return $this->stafDenganIzin($actor, 'nilai.view');
    }

    /** Entry is open only while the grade is not yet locked. */
    public function update(Authenticatable $actor, Nilai $nilai): bool
    {
        if ($nilai->is_final) {
            return false;
        }

        return $this->dosen($actor)
            && $this->izin($actor, 'nilai.manage')
            && $this->mengampu($actor->getKey(), $nilai);
    }

    public function finalize(Authenticatable $actor, Nilai $nilai): bool
    {
        return $this->update($actor, $nilai);
    }

    /**
     * Correcting a locked grade. Deliberately staff-only: a lecturer who could
     * silently reopen their own finalised grades would defeat the lock.
     */
    public function correct(Authenticatable $actor, Nilai $nilai): bool
    {
        return $this->stafDenganIzin($actor, 'nilai.manage');
    }

    private function mengampu(int $dosenId, Nilai $nilai): bool
    {
        return $nilai->kelasKuliah()
            ->whereHas('dosen', fn ($query) => $query->where('dosen.id', $dosenId))
            ->exists();
    }
}

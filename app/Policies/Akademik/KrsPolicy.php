<?php

declare(strict_types=1);

namespace App\Policies\Akademik;

use App\Models\Akademik\Krs;
use App\Policies\Concerns\ResolvesActor;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Study plan authorisation.
 *
 * Two rules carry real weight here: a student may only ever touch their own
 * plan, and only the assigned academic advisor may approve one — not any
 * lecturer who happens to hold the `krs.approve` permission.
 */
class KrsPolicy
{
    use ResolvesActor;

    public function viewAny(Authenticatable $actor): bool
    {
        return $this->mahasiswa($actor)
            || $this->izin($actor, 'krs.approve')
            || $this->stafDenganIzin($actor, 'krs.view');
    }

    public function view(Authenticatable $actor, Krs $krs): bool
    {
        if ($this->mahasiswa($actor)) {
            return $actor->getKey() === $krs->mahasiswa_id;
        }

        if ($this->dosen($actor)) {
            return $this->waliDari($actor->getKey(), $krs);
        }

        return $this->stafDenganIzin($actor, 'krs.view');
    }

    /** Only the owning student fills their plan, and only while it is editable. */
    public function update(Authenticatable $actor, Krs $krs): bool
    {
        if (!$this->mahasiswa($actor) || $actor->getKey() !== $krs->mahasiswa_id) {
            return $this->stafDenganIzin($actor, 'krs.manage');
        }

        return $krs->status->isEditable() && $this->izin($actor, 'krs.submit');
    }

    public function submit(Authenticatable $actor, Krs $krs): bool
    {
        return $this->update($actor, $krs);
    }

    /**
     * Approval belongs to the student's own academic advisor. A lecturer with
     * the permission but a different advisee list must still be refused —
     * otherwise the approval step means nothing.
     */
    public function approve(Authenticatable $actor, Krs $krs): bool
    {
        if ($this->staf($actor)) {
            return $this->izin($actor, 'krs.manage');
        }

        return $this->dosen($actor)
            && $this->izin($actor, 'krs.approve')
            && $this->waliDari($actor->getKey(), $krs);
    }

    public function reject(Authenticatable $actor, Krs $krs): bool
    {
        return $this->approve($actor, $krs);
    }

    private function waliDari(int $dosenId, Krs $krs): bool
    {
        return $krs->mahasiswa()->where('dosen_wali_id', $dosenId)->exists();
    }
}

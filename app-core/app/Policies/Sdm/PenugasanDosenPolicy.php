<?php

declare(strict_types=1);

namespace App\Policies\Sdm;

use App\Models\Sdm\PenugasanDosen;
use App\Policies\Concerns\ResolvesActor;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * A lecturer's recorded activity and the evidence behind it.
 *
 * The lecturer records their own and reads their own; staff read with
 * dosen.view and verify with dosen.manage. Verification turns a claim into
 * IKU evidence, so nobody verifies what they recorded themselves — a
 * lecturer has no path to it at all.
 */
class PenugasanDosenPolicy
{
    use ResolvesActor;

    public function view(Authenticatable $actor, PenugasanDosen $penugasan): bool
    {
        if ($this->dosen($actor)) {
            return $actor->getKey() === $penugasan->dosen_id;
        }

        return $this->stafDenganIzin($actor, 'dosen.view');
    }

    /** A lecturer, on their own portfolio; the row is always theirs. */
    public function create(Authenticatable $actor): bool
    {
        return $this->dosen($actor);
    }

    public function verifikasi(Authenticatable $actor, PenugasanDosen $penugasan): bool
    {
        return $this->stafDenganIzin($actor, 'dosen.manage');
    }
}

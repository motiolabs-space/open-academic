<?php

declare(strict_types=1);

namespace App\Policies\Sdm;

use App\Models\Sdm\AngkaKreditDosen;
use App\Models\Sdm\Dosen;
use App\Policies\Concerns\ResolvesActor;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * The credit ledger.
 *
 * A lecturer reads their own; the personnel office reads everybody's with
 * dosen.view and writes with dosen.manage. The penyetaraan sheet carries every
 * eligible lecturer's NUPTK and name to a ministry upload, so it is a write
 * permission's act, not a read one's.
 */
class AngkaKreditDosenPolicy
{
    use ResolvesActor;

    public function viewAny(Authenticatable $actor): bool
    {
        return $this->stafDenganIzin($actor, 'dosen.view');
    }

    /** Called as `can('rekap', [AngkaKreditDosen::class, $dosen])`. */
    public function rekap(Authenticatable $actor, Dosen $dosen): bool
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

    /** Office-entered lines only; derived lines follow their source. */
    public function delete(Authenticatable $actor, AngkaKreditDosen $baris): bool
    {
        return $this->stafDenganIzin($actor, 'dosen.manage') && !$baris->sumber->turunan();
    }

    public function susun(Authenticatable $actor): bool
    {
        return $this->stafDenganIzin($actor, 'dosen.manage');
    }

    public function ekspor(Authenticatable $actor): bool
    {
        return $this->stafDenganIzin($actor, 'dosen.manage');
    }
}

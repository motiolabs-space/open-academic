<?php

declare(strict_types=1);

namespace App\Policies\Keuangan;

use App\Models\Keuangan\Tagihan;
use App\Policies\Concerns\ResolvesActor;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Invoice authorisation.
 *
 * A student's outstanding balance is sensitive: it reveals their UKT band,
 * which in Indonesian universities is assigned from household income. Lecturers
 * therefore get no access at all, advisor or not.
 */
class TagihanPolicy
{
    use ResolvesActor;

    public function viewAny(Authenticatable $actor): bool
    {
        return $this->mahasiswa($actor) || $this->stafDenganIzin($actor, 'keuangan.view');
    }

    public function view(Authenticatable $actor, Tagihan $tagihan): bool
    {
        if ($this->mahasiswa($actor)) {
            return $actor->getKey() === $tagihan->mahasiswa_id;
        }

        return $this->stafDenganIzin($actor, 'keuangan.view');
    }

    public function create(Authenticatable $actor): bool
    {
        return $this->stafDenganIzin($actor, 'keuangan.manage');
    }

    public function update(Authenticatable $actor, Tagihan $tagihan): bool
    {
        return $this->stafDenganIzin($actor, 'keuangan.manage');
    }

    /** Paying is the one write action the owning student may perform. */
    public function pay(Authenticatable $actor, Tagihan $tagihan): bool
    {
        return $this->mahasiswa($actor)
            && $actor->getKey() === $tagihan->mahasiswa_id
            && $this->izin($actor, 'tagihan.view');
    }

    /** Waiving the KRS payment gate is a finance-office decision. */
    public function grantDispensation(Authenticatable $actor, Tagihan $tagihan): bool
    {
        return $this->stafDenganIzin($actor, 'keuangan.manage');
    }
}

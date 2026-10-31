<?php

declare(strict_types=1);

namespace App\Policies\Keuangan;

use App\Models\Keuangan\BeasiswaAjuan;
use App\Policies\Concerns\ResolvesActor;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Scholarship applications.
 *
 * An application carries household documents — the same reason TagihanPolicy
 * keeps lecturers out of invoices. A student sees their own; the finance
 * office sees all of them and is the only one that decides.
 */
class BeasiswaAjuanPolicy
{
    use ResolvesActor;

    public function create(Authenticatable $actor): bool
    {
        return $this->mahasiswa($actor) && $this->izin($actor, 'beasiswa.ajukan');
    }

    public function view(Authenticatable $actor, BeasiswaAjuan $ajuan): bool
    {
        if ($this->mahasiswa($actor)) {
            return $actor->getKey() === $ajuan->mahasiswa_id;
        }

        return $this->stafDenganIzin($actor, 'keuangan.view');
    }

    /** Reading the applications to a call. */
    public function viewAny(Authenticatable $actor): bool
    {
        return $this->stafDenganIzin($actor, 'keuangan.view');
    }

    /** Opening and closing calls, and deciding applications. */
    public function decide(Authenticatable $actor, ?BeasiswaAjuan $ajuan = null): bool
    {
        return $this->stafDenganIzin($actor, 'keuangan.manage');
    }
}

<?php

declare(strict_types=1);

namespace App\Policies\Kemahasiswaan;

use App\Policies\Concerns\ResolvesActor;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Who may see graduates' contact details, send them out, and record what
 * the tracer study found (ADR-037 §2).
 *
 * Three permissions, because they are three different exposures. Reading the
 * list shows who is reachable without showing how; managing it shows and
 * edits the contacts; exporting puts them in a file that leaves the system,
 * so it is granted separately and every use is logged.
 */
class AlumniPolicy
{
    use ResolvesActor;

    public function viewAny(Authenticatable $actor): bool
    {
        return $this->stafDenganIzin($actor, 'alumni.view')
            || $this->stafDenganIzin($actor, 'alumni.manage');
    }

    /** Seeing the actual addresses and numbers, and correcting them. */
    public function kelolaKontak(Authenticatable $actor): bool
    {
        return $this->stafDenganIzin($actor, 'alumni.manage');
    }

    public function ekspor(Authenticatable $actor): bool
    {
        return $this->stafDenganIzin($actor, 'alumni.ekspor');
    }

    public function imporStatus(Authenticatable $actor): bool
    {
        return $this->stafDenganIzin($actor, 'alumni.manage');
    }
}

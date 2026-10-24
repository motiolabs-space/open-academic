<?php

declare(strict_types=1);

namespace App\Policies\Helpdesk;

use App\Enums\StatusTiket;
use App\Models\Helpdesk\HelpdeskTiket;
use App\Policies\Concerns\ResolvesActor;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Who may see and act on a ticket.
 *
 * Three circles, from the inside out:
 *
 *  - the applicant sees exactly their own tickets;
 *  - a staff member with `helpdesk.tangani` sees their unit's queue and
 *    anything assigned to them by name;
 *  - a staff member with `helpdesk.manage` sees the whole campus.
 *
 * Nothing in here lets a lecturer read a student's ticket or a student read a
 * classmate's — there is no "advisor" circle, deliberately. A ticket can carry
 * a bank transfer receipt or a medical note, and the applicant filed it with
 * one office in mind.
 */
class HelpdeskTiketPolicy
{
    use ResolvesActor;

    public function viewAny(Authenticatable $actor): bool
    {
        if ($this->mahasiswa($actor) || $this->dosen($actor)) {
            return $this->izin($actor, 'helpdesk.ajukan');
        }

        return $this->stafDenganIzin($actor, 'helpdesk.view');
    }

    public function view(Authenticatable $actor, HelpdeskTiket $tiket): bool
    {
        if ($this->mahasiswa($actor) || $this->dosen($actor)) {
            return $tiket->milik($actor);
        }

        if (!$this->stafDenganIzin($actor, 'helpdesk.view')) {
            return false;
        }

        return $this->dalamLingkup($actor, $tiket);
    }

    public function create(Authenticatable $actor): bool
    {
        return ($this->mahasiswa($actor) || $this->dosen($actor))
            && $this->izin($actor, 'helpdesk.ajukan');
    }

    /** A public reply. */
    public function reply(Authenticatable $actor, HelpdeskTiket $tiket): bool
    {
        if ($tiket->status === StatusTiket::Ditutup) {
            return false;
        }

        if ($this->mahasiswa($actor) || $this->dosen($actor)) {
            return $tiket->milik($actor);
        }

        return $this->handle($actor, $tiket);
    }

    /** Disposition, status moves, internal notes. */
    public function handle(Authenticatable $actor, HelpdeskTiket $tiket): bool
    {
        return $this->stafDenganIzin($actor, 'helpdesk.tangani')
            && $this->dalamLingkup($actor, $tiket);
    }

    /** Categories and FAQ — campus-wide settings, not a unit's business. */
    public function manage(Authenticatable $actor): bool
    {
        return $this->stafDenganIzin($actor, 'helpdesk.manage');
    }

    private function dalamLingkup(Authenticatable $actor, HelpdeskTiket $tiket): bool
    {
        if ($this->izin($actor, 'helpdesk.manage')) {
            return true;
        }

        if ((int) $tiket->petugas_staff_id === (int) $actor->getKey()) {
            return true;
        }

        $unit = $actor->getAttribute('unit_kerja_id');

        return $unit !== null && (int) $tiket->unit_kerja_id === (int) $unit;
    }
}

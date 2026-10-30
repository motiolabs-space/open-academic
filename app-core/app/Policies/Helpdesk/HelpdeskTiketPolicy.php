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
 *  - the applicant sees exactly their own tickets — a student, a lecturer,
 *    or a member of staff who filed to another unit;
 *  - a staff member with `helpdesk.tangani` sees their unit's queue and
 *    anything assigned to them by name;
 *  - a staff member with `helpdesk.manage` sees the whole campus.
 *
 * Nothing in here lets a lecturer read a student's ticket or a student read a
 * classmate's — there is no "advisor" circle, deliberately. A ticket can carry
 * a bank transfer receipt or a medical note, and the applicant filed it with
 * one office in mind.
 *
 * And nobody handles their own ticket. A member of staff who filed one is its
 * applicant even if it is later dispositioned to their unit: they reply as the
 * applicant, and the officers of that unit do the handling.
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

    /** The applicant's own list, in whichever portal. */
    public function viewOwn(Authenticatable $actor): bool
    {
        return $this->izin($actor, 'helpdesk.ajukan');
    }

    public function view(Authenticatable $actor, HelpdeskTiket $tiket): bool
    {
        if ($this->mahasiswa($actor) || $this->dosen($actor)) {
            return $tiket->milik($actor);
        }

        if ($this->staf($actor) && $tiket->milik($actor)) {
            return true;
        }

        if (!$this->stafDenganIzin($actor, 'helpdesk.view')) {
            return false;
        }

        return $this->dalamLingkup($actor, $tiket);
    }

    /**
     * The staff screen of a ticket, internal notes included.
     *
     * Not the same as view(): an applicant who is staff sees their own ticket
     * on the applicant's screen, never on this one — even when it sits in
     * their unit's queue.
     */
    public function viewInternal(Authenticatable $actor, HelpdeskTiket $tiket): bool
    {
        return $this->stafDenganIzin($actor, 'helpdesk.view')
            && !$tiket->milik($actor)
            && $this->dalamLingkup($actor, $tiket);
    }

    public function create(Authenticatable $actor): bool
    {
        return $this->izin($actor, 'helpdesk.ajukan');
    }

    /**
     * Typing a ticket in for a student or lecturer at the front desk. Held by
     * the officers who handle tickets; it gives no later access to the ticket
     * beyond what their unit or name already gives.
     */
    public function createOnBehalf(Authenticatable $actor): bool
    {
        return $this->stafDenganIzin($actor, 'helpdesk.tangani');
    }

    /** A public reply. */
    public function reply(Authenticatable $actor, HelpdeskTiket $tiket): bool
    {
        if ($tiket->status === StatusTiket::Ditutup) {
            return false;
        }

        if ($tiket->milik($actor)) {
            return true;
        }

        if ($this->mahasiswa($actor) || $this->dosen($actor)) {
            return false;
        }

        return $this->handle($actor, $tiket);
    }

    /** Disposition, status moves, internal notes. */
    public function handle(Authenticatable $actor, HelpdeskTiket $tiket): bool
    {
        return $this->stafDenganIzin($actor, 'helpdesk.tangani')
            && !$tiket->milik($actor)
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

<?php

declare(strict_types=1);

namespace App\Policies\Pmb;

use App\Models\Pmb\PmbAkun;
use App\Models\Pmb\PmbPendaftar;
use App\Policies\Concerns\ResolvesActor;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Who may see and change an application.
 *
 * Two circles that never overlap. An applicant sees exactly the applications
 * their own account opened — never one the desk keyed in under the same
 * email, which may be a sibling's. Staff see applications by permission.
 * Students and lecturers see none.
 *
 * Whether an action is *possible* at this stage (a draft can be edited, a
 * decided application cannot be withdrawn) is the service's to refuse with a
 * reason; this answers only whose application it is.
 */
class PmbPendaftarPolicy
{
    use ResolvesActor;

    public function view(Authenticatable $actor, PmbPendaftar $pendaftar): bool
    {
        if ($actor instanceof PmbAkun) {
            return $pendaftar->milik($actor);
        }

        return $this->stafDenganIzin($actor, 'pmb.view');
    }

    /** Biodata, documents, submission, withdrawal — the applicant's own form. */
    public function update(Authenticatable $actor, PmbPendaftar $pendaftar): bool
    {
        return $actor instanceof PmbAkun && $pendaftar->milik($actor);
    }

    /** Checking documents and deciding selection. */
    public function manage(Authenticatable $actor, PmbPendaftar $pendaftar): bool
    {
        return $this->stafDenganIzin($actor, 'pmb.manage');
    }

    /**
     * Recording the form fee.
     *
     * The cashier, or the admissions desk where it takes the money itself —
     * common on a small campus during registration week.
     */
    public function recordPayment(Authenticatable $actor, PmbPendaftar $pendaftar): bool
    {
        return $this->stafDenganIzin($actor, 'keuangan.manage')
            || $this->stafDenganIzin($actor, 'pmb.manage');
    }
}

<?php

declare(strict_types=1);

namespace App\Policies\Sdm;

use App\Models\Sdm\Staff;
use App\Policies\Concerns\ResolvesActor;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Who may administer staff accounts, and which roles they may hand out.
 *
 * `pengaturan.manage` also gates announcements, SPMI and performance targets,
 * so a campus can reasonably give it to a public-relations or quality-office
 * role. Without these limits that role could edit its own account to
 * super-admin, or reset a super-admin's password and read the new one off the
 * screen. The top role is therefore granted, edited and reset only by someone
 * who already holds it, and nobody changes their own role.
 */
class StaffPolicy
{
    use ResolvesActor;

    public const PERAN_TERTINGGI = 'super-admin';

    /** Edit, deactivate, reactivate, or reset the password of an account. */
    public function manage(Authenticatable $actor, Staff $sasaran): bool
    {
        if (!$this->stafDenganIzin($actor, 'pengaturan.manage')) {
            return false;
        }

        return !$sasaran->hasRole(self::PERAN_TERTINGGI) || $this->tertinggi($actor);
    }

    /** Give `$peran` to `$sasaran`; null for an account being created. */
    public function assignRole(Authenticatable $actor, ?Staff $sasaran, string $peran): bool
    {
        if (!$this->stafDenganIzin($actor, 'pengaturan.manage')) {
            return false;
        }

        if ($sasaran !== null && $sasaran->is($actor) && !$sasaran->hasRole($peran)) {
            return false;
        }

        return $peran !== self::PERAN_TERTINGGI || $this->tertinggi($actor);
    }

    private function tertinggi(Authenticatable $actor): bool
    {
        return $actor instanceof Staff && $actor->hasRole(self::PERAN_TERTINGGI);
    }
}

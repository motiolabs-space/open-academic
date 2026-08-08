<?php

declare(strict_types=1);

namespace App\Policies\Concerns;

use App\Enums\UserRole;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\Staff;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Shared vocabulary for every policy.
 *
 * Three guards means a policy method receives one of three unrelated model
 * types as its actor. Rather than repeating `instanceof` chains in forty
 * methods, each policy asks these questions instead.
 *
 * Note that `izin()` checks the Spatie permission on the actor's own guard —
 * a "krs.view" permission granted to staff is a different record from the one
 * granted to dosen, and must never satisfy the other.
 */
trait ResolvesActor
{
    protected function peran(Authenticatable $actor): ?UserRole
    {
        return match (true) {
            $actor instanceof Staff => UserRole::Staff,
            $actor instanceof Dosen => UserRole::Dosen,
            $actor instanceof Mahasiswa => UserRole::Mahasiswa,
            default => null,
        };
    }

    protected function staf(Authenticatable $actor): bool
    {
        return $actor instanceof Staff;
    }

    protected function dosen(Authenticatable $actor): bool
    {
        return $actor instanceof Dosen;
    }

    protected function mahasiswa(Authenticatable $actor): bool
    {
        return $actor instanceof Mahasiswa;
    }

    /** Spatie permission check, scoped to the actor's own guard. */
    protected function izin(Authenticatable $actor, string $permission): bool
    {
        if (!method_exists($actor, 'hasPermissionTo')) {
            return false;
        }

        $peran = $this->peran($actor);

        if ($peran === null) {
            return false;
        }

        // hasPermissionTo throws when the permission does not exist for the
        // guard at all; an absent permission means "not allowed", not a crash.
        try {
            return $actor->hasPermissionTo($permission, $peran->guard());
        } catch (\Throwable) {
            return false;
        }
    }

    /** Staff with a given permission — the most common admin rule. */
    protected function stafDenganIzin(Authenticatable $actor, string $permission): bool
    {
        return $this->staf($actor) && $this->izin($actor, $permission);
    }
}

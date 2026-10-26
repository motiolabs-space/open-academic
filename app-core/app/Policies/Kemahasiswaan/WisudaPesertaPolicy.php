<?php

declare(strict_types=1);

namespace App\Policies\Kemahasiswaan;

use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Kemahasiswaan\WisudaPeserta;
use App\Policies\Concerns\ResolvesActor;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Who may put a graduate on a ceremony list, or take them off it.
 *
 * A student acts for themself only. There is no circle for classmates and none
 * for lecturers: registering is the graduate's own choice, and removing
 * somebody else's name is not a favour anyone needs to be able to do.
 *
 * Staff with `wisuda.manage` act for anyone. Whether the graduate *qualifies*
 * is not decided here — that is SyaratWisudaService, read by both sides.
 */
class WisudaPesertaPolicy
{
    use ResolvesActor;

    public function viewAny(Authenticatable $actor): bool
    {
        return $this->mahasiswa($actor) || $this->stafDenganIzin($actor, 'wisuda.view');
    }

    /** Registering $untuk for a ceremony. */
    public function create(Authenticatable $actor, Mahasiswa $untuk): bool
    {
        if ($this->mahasiswa($actor)) {
            return (int) $actor->getKey() === (int) $untuk->getKey();
        }

        return $this->stafDenganIzin($actor, 'wisuda.manage');
    }

    public function delete(Authenticatable $actor, WisudaPeserta $peserta): bool
    {
        if (filled($peserta->nomor_ijazah)) {
            return false;
        }

        if ($this->mahasiswa($actor)) {
            // Only while the list is still open; see WisudaService::batalkanSendiri().
            return (int) $peserta->yudisium?->mahasiswa_id === (int) $actor->getKey()
                && (bool) $peserta->periode?->is_pendaftaran_dibuka;
        }

        return $this->stafDenganIzin($actor, 'wisuda.manage');
    }
}

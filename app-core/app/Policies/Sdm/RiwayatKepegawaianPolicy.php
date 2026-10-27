<?php

declare(strict_types=1);

namespace App\Policies\Sdm;

use App\Enums\JenisRiwayat;
use App\Enums\StatusRiwayat;
use App\Models\Sdm\Dosen;
use App\Policies\Concerns\ResolvesActor;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Who may see, file, change, and vouch for a personnel-history row.
 *
 * Registered for all fourteen history models (see AppServiceProvider). A
 * lecturer handles only their own rows, only the kinds they file, and only
 * until somebody has approved them; staff read with dosen.view and write or
 * review with dosen.manage. Students never reach any of it.
 */
class RiwayatKepegawaianPolicy
{
    use ResolvesActor;

    public function viewAny(Authenticatable $actor): bool
    {
        return $this->stafDenganIzin($actor, 'dosen.view');
    }

    public function view(Authenticatable $actor, Model $baris): bool
    {
        if ($this->dosen($actor)) {
            return $actor->getKey() === $baris->dosen_id;
        }

        return $this->stafDenganIzin($actor, 'dosen.view');
    }

    /**
     * Filing a new row of a kind, for a lecturer.
     *
     * Called as `can('create', [$modelClass, $pemilik, $jenis])`.
     */
    public function create(Authenticatable $actor, Dosen $pemilik, JenisRiwayat $jenis): bool
    {
        if ($this->dosen($actor)) {
            return $actor->getKey() === $pemilik->getKey()
                && $jenis->diisiDosen()
                && $this->izin($actor, 'portofolio.kelola');
        }

        return $this->stafDenganIzin($actor, 'dosen.manage');
    }

    public function update(Authenticatable $actor, Model $baris): bool
    {
        if ($this->dosen($actor)) {
            return $actor->getKey() === $baris->dosen_id
                && JenisRiwayat::dariModel($baris)->diisiDosen()
                && $baris->status->dapatDisunting()
                && $this->izin($actor, 'portofolio.kelola');
        }

        return $this->stafDenganIzin($actor, 'dosen.manage');
    }

    public function delete(Authenticatable $actor, Model $baris): bool
    {
        return $this->update($actor, $baris);
    }

    /**
     * Approving or rejecting — staff only, and only what is still waiting.
     *
     * Never the lecturer, whatever else they hold: approving one's own file is
     * not a review.
     */
    public function validasi(Authenticatable $actor, Model $baris): bool
    {
        return $this->stafDenganIzin($actor, 'dosen.manage')
            && $baris->status === StatusRiwayat::Diajukan;
    }
}

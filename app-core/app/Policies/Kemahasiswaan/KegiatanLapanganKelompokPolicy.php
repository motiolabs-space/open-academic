<?php

declare(strict_types=1);

namespace App\Policies\Kemahasiswaan;

use App\Models\Kemahasiswaan\KegiatanLapanganKelompok;
use App\Policies\Concerns\ResolvesActor;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Who may see a field-programme group and act on its members.
 *
 * A lecturer's reach is decided per group by `dosen_pembimbing_id`, never by
 * role: every lecturer may be a field supervisor, and a role-level grant
 * would let any of them mark any group — the same reasoning as
 * tugas_akhir.bimbing.
 */
class KegiatanLapanganKelompokPolicy
{
    use ResolvesActor;

    public function view(Authenticatable $actor, KegiatanLapanganKelompok $kelompok): bool
    {
        if ($this->staf($actor)) {
            return $this->izin($actor, 'mahasiswa.view');
        }

        if ($this->dosen($actor)) {
            return (int) $kelompok->dosen_pembimbing_id === (int) $actor->getKey();
        }

        if ($this->mahasiswa($actor)) {
            return $kelompok->anggota()->where('mahasiswa_id', $actor->getKey())->exists();
        }

        return false;
    }

    /** Marking members and reading their logs. */
    public function bimbing(Authenticatable $actor, KegiatanLapanganKelompok $kelompok): bool
    {
        if ($this->dosen($actor)) {
            return (int) $kelompok->dosen_pembimbing_id === (int) $actor->getKey();
        }

        return $this->stafDenganIzin($actor, 'mahasiswa.manage');
    }

    public function update(Authenticatable $actor, KegiatanLapanganKelompok $kelompok): bool
    {
        return $this->stafDenganIzin($actor, 'mahasiswa.manage');
    }
}

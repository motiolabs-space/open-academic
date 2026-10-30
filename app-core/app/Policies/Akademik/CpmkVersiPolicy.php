<?php

declare(strict_types=1);

namespace App\Policies\Akademik;

use App\Models\Akademik\CpmkVersi;
use App\Models\Akademik\KelasKuliah;
use App\Models\Akademik\MataKuliah;
use App\Models\Akademik\TahunAkademik;
use App\Policies\Concerns\ResolvesActor;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Who may write a course's CPMK set.
 *
 * Two hands, on purpose: the programme (staff with master.manage), which owns
 * the curriculum, and the lecturers teaching the course this term, who write
 * the RPS and know what the course actually promises. Nobody else — a
 * lecturer of another course rewording this one's outcomes would move the
 * attainment figures of students they never taught.
 */
class CpmkVersiPolicy
{
    use ResolvesActor;

    public function viewAny(Authenticatable $actor): bool
    {
        return $this->stafDenganIzin($actor, 'master.view');
    }

    /** Starting or revising the set of one course. */
    public function create(Authenticatable $actor, MataKuliah $mataKuliah): bool
    {
        if ($this->stafDenganIzin($actor, 'master.manage')) {
            return true;
        }

        return $this->dosen($actor) && $this->mengampu($actor->getKey(), $mataKuliah);
    }

    /** Editing a draft. A version in force is closed to everyone. */
    public function update(Authenticatable $actor, CpmkVersi $versi): bool
    {
        return $versi->status->dapatDisunting()
            && $this->create($actor, $versi->mataKuliah);
    }

    public function publish(Authenticatable $actor, CpmkVersi $versi): bool
    {
        return $this->update($actor, $versi);
    }

    private function mengampu(int $dosenId, MataKuliah $mataKuliah): bool
    {
        $term = TahunAkademik::aktif();

        return $term !== null && KelasKuliah::query()
            ->where('mata_kuliah_id', $mataKuliah->id)
            ->where('tahun_akademik_id', $term->id)
            ->whereHas('dosen', fn ($q) => $q->where('dosen.id', $dosenId))
            ->exists();
    }
}

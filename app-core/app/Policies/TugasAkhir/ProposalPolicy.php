<?php

declare(strict_types=1);

namespace App\Policies\TugasAkhir;

use App\Enums\StatusProposal;
use App\Models\TugasAkhir\Proposal;
use App\Models\TugasAkhir\TugasAkhir;
use App\Policies\Concerns\ResolvesActor;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Who may submit, read, and decide a final-project proposal (#38).
 *
 * The student submits for their own project only. A lecturer reads and decides
 * a proposal only when it names them as prospective supervisor — decided per
 * version, never by role, as with supervision itself — and reads it again once
 * they supervise the project. Staff read with tugas_akhir.view and decide with
 * tugas_akhir.manage, which is how a programme coordinator acts here.
 */
class ProposalPolicy
{
    use ResolvesActor;

    public function create(Authenticatable $actor, TugasAkhir $ta): bool
    {
        return $this->mahasiswa($actor)
            && $this->izin($actor, 'tugas_akhir.ajukan')
            && (int) $ta->mahasiswa_id === (int) $actor->getKey();
    }

    public function view(Authenticatable $actor, Proposal $proposal): bool
    {
        if ($this->staf($actor)) {
            return $this->izin($actor, 'tugas_akhir.view');
        }

        if ($this->mahasiswa($actor)) {
            return (int) $proposal->tugasAkhir->mahasiswa_id === (int) $actor->getKey();
        }

        if ($this->dosen($actor)) {
            return (int) $proposal->calon_pembimbing_dosen_id === (int) $actor->getKey()
                || $proposal->tugasAkhir->pembimbing()->where('dosen_id', $actor->getKey())->exists();
        }

        return false;
    }

    public function telaah(Authenticatable $actor, Proposal $proposal): bool
    {
        if ($proposal->status !== StatusProposal::Menunggu) {
            return false;
        }

        if ($this->staf($actor)) {
            return $this->izin($actor, 'tugas_akhir.manage');
        }

        return $this->dosen($actor)
            && $this->izin($actor, 'tugas_akhir.bimbing')
            && (int) $proposal->calon_pembimbing_dosen_id === (int) $actor->getKey();
    }
}

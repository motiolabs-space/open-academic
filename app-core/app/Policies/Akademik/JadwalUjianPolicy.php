<?php

declare(strict_types=1);

namespace App\Policies\Akademik;

use App\Enums\KrsStatus;
use App\Models\Akademik\JadwalUjian;
use App\Policies\Concerns\ResolvesActor;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Who may see and arrange exam sessions.
 *
 * Arranging is the registrar's, under the same permission that opens classes
 * and puts them on the timetable: an exam session is a property of a class
 * offering, and splitting the grant would let someone place exams for classes
 * they cannot see the timetable of.
 *
 * Outside the registrar's screen only published sessions exist. A student sees
 * the sessions of their own approved study plan; a lecturer sees those they
 * teach or invigilate.
 */
class JadwalUjianPolicy
{
    use ResolvesActor;

    public function viewAny(Authenticatable $actor): bool
    {
        return $this->stafDenganIzin($actor, 'kelas.view')
            || $this->dosen($actor)
            || ($this->mahasiswa($actor) && $this->izin($actor, 'jadwal.view'));
    }

    public function view(Authenticatable $actor, JadwalUjian $jadwal): bool
    {
        if ($this->staf($actor)) {
            return $this->izin($actor, 'kelas.view');
        }

        if (!$jadwal->sudahTerbit()) {
            return false;
        }

        if ($this->dosen($actor)) {
            return $jadwal->kelasKuliah->dosen()->where('dosen.id', $actor->getKey())->exists()
                || $jadwal->pengawas()->where('dosen_id', $actor->getKey())->exists();
        }

        if ($this->mahasiswa($actor)) {
            return $this->izin($actor, 'jadwal.view')
                && $jadwal->kelasKuliah->krsDetail()
                    ->whereHas('krs', fn ($q) => $q
                        ->where('mahasiswa_id', $actor->getKey())
                        ->where('status', KrsStatus::Disetujui->value))
                    ->exists();
        }

        return false;
    }

    public function create(Authenticatable $actor): bool
    {
        return $this->stafDenganIzin($actor, 'kelas.manage');
    }

    public function update(Authenticatable $actor, JadwalUjian $jadwal): bool
    {
        return $this->stafDenganIzin($actor, 'kelas.manage');
    }

    public function delete(Authenticatable $actor, JadwalUjian $jadwal): bool
    {
        return $this->stafDenganIzin($actor, 'kelas.manage') && !$jadwal->sudahTerbit();
    }

    /** Publishing a term's drafts — the moment the clash check is enforced. */
    public function publish(Authenticatable $actor): bool
    {
        return $this->stafDenganIzin($actor, 'kelas.manage');
    }
}

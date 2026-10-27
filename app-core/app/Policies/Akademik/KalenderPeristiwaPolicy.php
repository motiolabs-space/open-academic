<?php

declare(strict_types=1);

namespace App\Policies\Akademik;

use App\Enums\SasaranPeristiwa;
use App\Models\Akademik\KalenderPeristiwa;
use App\Policies\Concerns\ResolvesActor;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Who may read the academic calendar and who writes on it.
 *
 * Reading is open to every portal — a calendar nobody may see is not one — and
 * narrowed by audience: staff see every entry, a lecturer what is aimed at
 * lecturers, students or everyone, a student what is aimed at students or
 * everyone within their own programme. KalenderService applies the same rule
 * to its lists; this policy answers it for one entry.
 *
 * Writing is the registrar's, under its own permission rather than
 * `kelas.manage`: a holiday is not a property of a class offering, and the
 * office that declares one is not always the one that opens classes.
 */
class KalenderPeristiwaPolicy
{
    use ResolvesActor;

    public function viewAny(Authenticatable $actor): bool
    {
        return $this->staf($actor)
            || $this->dosen($actor)
            || ($this->mahasiswa($actor) && $this->izin($actor, 'jadwal.view'));
    }

    public function view(Authenticatable $actor, KalenderPeristiwa $peristiwa): bool
    {
        if ($this->staf($actor)) {
            return $peristiwa->sudahTerbit() || $this->izin($actor, 'kalender.manage');
        }

        if (!$peristiwa->sudahTerbit() || !$this->viewAny($actor)) {
            return false;
        }

        $peran = $this->peran($actor);

        if ($peran === null || !in_array($peristiwa->sasaran, SasaranPeristiwa::terlihatOleh($peran), true)) {
            return false;
        }

        return !$this->mahasiswa($actor)
            || $peristiwa->prodi_id === null
            || $peristiwa->prodi_id === $actor->getAttribute('prodi_id');
    }

    /** A personal iCalendar link: anyone who may read the calendar may take it home. */
    public function subscribe(Authenticatable $actor): bool
    {
        return $this->viewAny($actor);
    }

    /** Whether unpublished entries show on the calendar screen. */
    public function viewDraft(Authenticatable $actor): bool
    {
        return $this->stafDenganIzin($actor, 'kalender.manage');
    }

    public function create(Authenticatable $actor): bool
    {
        return $this->stafDenganIzin($actor, 'kalender.manage');
    }

    public function update(Authenticatable $actor, KalenderPeristiwa $peristiwa): bool
    {
        return $this->stafDenganIzin($actor, 'kalender.manage');
    }

    public function delete(Authenticatable $actor, KalenderPeristiwa $peristiwa): bool
    {
        return $this->stafDenganIzin($actor, 'kalender.manage');
    }
}

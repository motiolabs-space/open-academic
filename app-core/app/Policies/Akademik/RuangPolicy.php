<?php

declare(strict_types=1);

namespace App\Policies\Akademik;

use App\Policies\Concerns\ResolvesActor;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Who may read how the campus's rooms are used.
 *
 * Its own permission rather than kelas.view: facilities staff need to see
 * which rooms sit empty and which overflow without being handed the class
 * timetable's editing screens, and BAAK needs it while writing that timetable.
 * Lecturers and students never see it — the report names every class in every
 * room.
 *
 * Maintaining rooms themselves stays under master.manage, in the master-data
 * screen.
 */
class RuangPolicy
{
    use ResolvesActor;

    public function viewUtilization(Authenticatable $actor): bool
    {
        return $this->stafDenganIzin($actor, 'ruang.view');
    }
}

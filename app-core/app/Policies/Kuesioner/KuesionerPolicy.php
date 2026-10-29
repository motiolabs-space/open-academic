<?php

declare(strict_types=1);

namespace App\Policies\Kuesioner;

use App\Models\Kuesioner\Kuesioner;
use App\Policies\Concerns\ResolvesActor;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Who may write, read the results of, and answer a questionnaire.
 *
 * Writing and reading results are staff permissions. Answering is not a
 * permission at all: it is decided per questionnaire by its audience, so a
 * student of one programme cannot open a form put to another — not even to
 * look at the questions.
 */
class KuesionerPolicy
{
    use ResolvesActor;

    public function viewAny(Authenticatable $actor): bool
    {
        return $this->stafDenganIzin($actor, 'kuesioner.view');
    }

    /** Results, aggregated, and their export. */
    public function view(Authenticatable $actor, Kuesioner $kuesioner): bool
    {
        return $this->stafDenganIzin($actor, 'kuesioner.view');
    }

    public function manage(Authenticatable $actor): bool
    {
        return $this->stafDenganIzin($actor, 'kuesioner.manage');
    }

    /** Opening the form. Whether it still takes answers is the service's call. */
    public function respond(Authenticatable $actor, Kuesioner $kuesioner): bool
    {
        return $actor instanceof Model
            && $kuesioner->is_active
            && $kuesioner->menyasar($actor);
    }
}

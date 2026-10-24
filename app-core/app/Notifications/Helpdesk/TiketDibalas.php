<?php

declare(strict_types=1);

namespace App\Notifications\Helpdesk;

use App\Models\Helpdesk\HelpdeskBalasan;
use App\Models\Helpdesk\HelpdeskTiket;
use Illuminate\Support\Str;

/**
 * Staff replied publicly.
 *
 * Carries the first line of the reply, not the whole thing: the full text may
 * quote the applicant's own message back, and the notification list is read
 * on a phone in public.
 */
class TiketDibalas extends NotifikasiTiket
{
    public function __construct(
        HelpdeskTiket $tiket,
        public readonly HelpdeskBalasan $balasan,
    ) {
        parent::__construct($tiket);
    }

    public function judul(object $penerima): string
    {
        return 'Balasan untuk tiket '.$this->tiket->nomor;
    }

    public function ringkasan(object $penerima): string
    {
        return Str::limit(trim((string) $this->balasan->isi), 140);
    }

    public function ajakan(): string
    {
        return 'Baca Balasan';
    }
}

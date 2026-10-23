<?php

declare(strict_types=1);

namespace App\Notifications\Helpdesk;

/**
 * The ticket has reached an office.
 *
 * Says which one. "Your ticket is being handled" tells the applicant nothing
 * they can act on; "the finance office has it" tells them where to go if the
 * portal reply never comes.
 */
class TiketDidisposisi extends NotifikasiTiket
{
    public function judul(object $penerima): string
    {
        return 'Tiket '.$this->tiket->nomor.' diteruskan ke '
            .($this->tiket->unitKerja?->nama ?? 'unit penanganan');
    }

    public function ringkasan(object $penerima): string
    {
        $petugas = $this->tiket->petugas?->nama;

        return sprintf(
            '"%s" kini ditangani %s%s.',
            $this->tiket->judul,
            $this->tiket->unitKerja?->nama ?? 'unit terkait',
            $petugas !== null ? ' ('.$petugas.')' : '',
        );
    }
}

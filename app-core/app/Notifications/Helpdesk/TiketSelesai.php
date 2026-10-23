<?php

declare(strict_types=1);

namespace App\Notifications\Helpdesk;

use App\Enums\StatusTiket;

/**
 * The ticket was resolved, or closed without resolution.
 *
 * One notification for both, because the applicant's next step is the same —
 * read the note — and the difference is in the tone, not the action.
 */
class TiketSelesai extends NotifikasiTiket
{
    public function judul(object $penerima): string
    {
        return $this->ditutup()
            ? 'Tiket '.$this->tiket->nomor.' ditutup'
            : 'Tiket '.$this->tiket->nomor.' selesai';
    }

    public function ringkasan(object $penerima): string
    {
        return $this->ditutup()
            ? '"'.$this->tiket->judul.'" ditutup oleh petugas. Buka tiket baru bila masalahnya berlanjut.'
            : '"'.$this->tiket->judul.'" dinyatakan selesai. Balas tiket ini bila jawabannya belum menyelesaikan masalah Anda.';
    }

    public function tone(): string
    {
        return $this->ditutup() ? 'info' : 'success';
    }

    private function ditutup(): bool
    {
        return $this->tiket->status === StatusTiket::Ditutup;
    }
}

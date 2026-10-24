<?php

declare(strict_types=1);

namespace App\Notifications\Helpdesk;

use App\Enums\KategoriNotifikasi;
use App\Models\Helpdesk\HelpdeskTiket;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Notifications\Notifikasi;

/**
 * What the three helpdesk notifications share: the category, and where the
 * link goes on whichever portal the recipient uses.
 *
 * Kept in one place so they cannot disagree about either.
 */
abstract class NotifikasiTiket extends Notifikasi
{
    public function __construct(public readonly HelpdeskTiket $tiket) {}

    public function kategori(): KategoriNotifikasi
    {
        return KategoriNotifikasi::Helpdesk;
    }

    public function tautan(object $penerima): ?string
    {
        return $penerima instanceof Mahasiswa
            ? route('mahasiswa.helpdesk.show', $this->tiket)
            : route('dosen.helpdesk.show', $this->tiket);
    }

    public function ajakan(): string
    {
        return 'Buka Tiket';
    }
}

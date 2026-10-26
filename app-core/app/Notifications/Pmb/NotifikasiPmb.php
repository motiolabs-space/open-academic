<?php

declare(strict_types=1);

namespace App\Notifications\Pmb;

use App\Enums\KategoriNotifikasi;
use App\Models\Pmb\PmbPendaftar;
use App\Notifications\Notifikasi;

/**
 * Shared shape of everything the admission office tells an applicant.
 *
 * Filed under Kemahasiswaan, which is mandatory: a selection result or a
 * returned document is a decision the applicant will be held to, exactly the
 * rule KategoriNotifikasi applies. Not a category of its own because the
 * category list is also the preference screen of every student and staff
 * member, who have nothing to set about admissions.
 */
abstract class NotifikasiPmb extends Notifikasi
{
    public function __construct(public readonly PmbPendaftar $pendaftar) {}

    public function kategori(): KategoriNotifikasi
    {
        return KategoriNotifikasi::Kemahasiswaan;
    }

    public function tautan(object $penerima): ?string
    {
        return route('pmb.portal.show', $this->pendaftar);
    }

    public function ajakan(): string
    {
        return __('pmb.notifikasi.ajakan');
    }
}

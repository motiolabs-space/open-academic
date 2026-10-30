<?php

declare(strict_types=1);

namespace App\Policies\Kemahasiswaan;

use App\Enums\JenisPoin;
use App\Models\Kemahasiswaan\PoinMahasiswa;
use App\Policies\Concerns\ResolvesActor;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Who may prepare the SIMKATMAWA report and fill in what it needs.
 *
 * The same staff who record and verify achievements: the report is their
 * ledger seen from the ministry's side, and it lists names and student
 * numbers, so it is not opened to read-only roles.
 */
class PoinMahasiswaPolicy
{
    use ResolvesActor;

    public function eksporSimkatmawa(Authenticatable $actor): bool
    {
        return $this->stafDenganIzin($actor, 'mahasiswa.manage');
    }

    public function lengkapiSimkatmawa(Authenticatable $actor, PoinMahasiswa $poin): bool
    {
        return $poin->jenis === JenisPoin::Prestasi
            && $this->stafDenganIzin($actor, 'mahasiswa.manage');
    }
}

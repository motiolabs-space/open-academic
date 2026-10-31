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

    /**
     * A student reporting an achievement of their own (ADR-040 §3). It joins
     * the same verification queue as anything staff record.
     */
    public function lapor(Authenticatable $actor): bool
    {
        return $this->mahasiswa($actor) && $this->izin($actor, 'prestasi.lapor');
    }

    /** A row and its evidence: the student it is about, and student affairs. */
    public function view(Authenticatable $actor, PoinMahasiswa $poin): bool
    {
        if ($this->mahasiswa($actor)) {
            return $actor->getKey() === $poin->mahasiswa_id;
        }

        return $this->stafDenganIzin($actor, 'mahasiswa.view');
    }
}

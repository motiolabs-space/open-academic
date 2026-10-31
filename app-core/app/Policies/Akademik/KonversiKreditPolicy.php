<?php

declare(strict_types=1);

namespace App\Policies\Akademik;

use App\Enums\StatusKonversi;
use App\Models\Akademik\KonversiKredit;
use App\Policies\Concerns\ResolvesActor;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Credit conversions.
 *
 * Assessing stays with the registrar (mahasiswa.manage). A student reads their
 * own conversions and may send a document only when one was asked for on a
 * proposal still waiting (ADR-040 §4).
 */
class KonversiKreditPolicy
{
    use ResolvesActor;

    public function view(Authenticatable $actor, KonversiKredit $konversi): bool
    {
        if ($this->mahasiswa($actor)) {
            return $actor->getKey() === $konversi->mahasiswa_id;
        }

        return $this->stafDenganIzin($actor, 'mahasiswa.view');
    }

    public function mintaDokumen(Authenticatable $actor, KonversiKredit $konversi): bool
    {
        return $konversi->status === StatusKonversi::Diajukan
            && $this->stafDenganIzin($actor, 'mahasiswa.manage');
    }

    public function unggahDokumen(Authenticatable $actor, KonversiKredit $konversi): bool
    {
        return $this->mahasiswa($actor)
            && $actor->getKey() === $konversi->mahasiswa_id
            && $konversi->status === StatusKonversi::Diajukan
            && $konversi->dokumen_diminta !== null;
    }
}

<?php

declare(strict_types=1);

namespace App\Policies\Kemahasiswaan;

use App\Models\Kemahasiswaan\AktivitasMahasiswa;
use App\Models\Kemahasiswaan\KegiatanLapanganLogbook;
use App\Policies\Concerns\ResolvesActor;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Who may write, read, withdraw and sign off a field-log entry.
 *
 * The student writes only in their own log; the group's field supervisor
 * reads and signs off; staff read. Nobody but the author withdraws, and the
 * author only until it has been signed off.
 */
class KegiatanLapanganLogbookPolicy
{
    use ResolvesActor;

    public function create(Authenticatable $actor, AktivitasMahasiswa $aktivitas): bool
    {
        return $this->mahasiswa($actor)
            && (int) $aktivitas->mahasiswa_id === (int) $actor->getKey()
            && $aktivitas->kegiatan_lapangan_id !== null;
    }

    public function view(Authenticatable $actor, KegiatanLapanganLogbook $logbook): bool
    {
        $aktivitas = $logbook->aktivitas;

        if ($this->mahasiswa($actor)) {
            return (int) $aktivitas->mahasiswa_id === (int) $actor->getKey();
        }

        if ($this->dosen($actor)) {
            return (int) $aktivitas->kelompok?->dosen_pembimbing_id === (int) $actor->getKey();
        }

        return $this->stafDenganIzin($actor, 'mahasiswa.view');
    }

    public function delete(Authenticatable $actor, KegiatanLapanganLogbook $logbook): bool
    {
        return $this->mahasiswa($actor)
            && (int) $logbook->aktivitas->mahasiswa_id === (int) $actor->getKey()
            && !$logbook->sudahDiperiksa();
    }

    public function periksa(Authenticatable $actor, KegiatanLapanganLogbook $logbook): bool
    {
        return $this->dosen($actor)
            && (int) $logbook->aktivitas->kelompok?->dosen_pembimbing_id === (int) $actor->getKey()
            && !$logbook->sudahDiperiksa();
    }
}

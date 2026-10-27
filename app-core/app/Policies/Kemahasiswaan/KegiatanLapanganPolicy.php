<?php

declare(strict_types=1);

namespace App\Policies\Kemahasiswaan;

use App\Models\Kemahasiswaan\KegiatanLapangan;
use App\Policies\Concerns\ResolvesActor;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Who may run a KKN/PKL programme.
 *
 * Under the student-data permissions, the same grant that verifies MBKM
 * activities on the IKU screen: a participant is an activity row, and the
 * office that vouches for one should be the office that vouches for the other.
 *
 * Lecturers and students never see the programme as a whole — only their own
 * group, through KegiatanLapanganKelompokPolicy.
 */
class KegiatanLapanganPolicy
{
    use ResolvesActor;

    public function viewAny(Authenticatable $actor): bool
    {
        return $this->stafDenganIzin($actor, 'mahasiswa.view');
    }

    public function view(Authenticatable $actor, KegiatanLapangan $kegiatan): bool
    {
        return $this->stafDenganIzin($actor, 'mahasiswa.view');
    }

    public function create(Authenticatable $actor): bool
    {
        return $this->stafDenganIzin($actor, 'mahasiswa.manage');
    }

    public function update(Authenticatable $actor, KegiatanLapangan $kegiatan): bool
    {
        return $this->stafDenganIzin($actor, 'mahasiswa.manage');
    }

    /** Turning marks into course grades — staff only, as every grade correction is. */
    public function konversi(Authenticatable $actor, KegiatanLapangan $kegiatan): bool
    {
        return $this->stafDenganIzin($actor, 'mahasiswa.manage');
    }

    /** A student signing themself up while registration is open. */
    public function daftar(Authenticatable $actor, KegiatanLapangan $kegiatan): bool
    {
        return $this->mahasiswa($actor) && $kegiatan->pendaftaran_dibuka;
    }
}

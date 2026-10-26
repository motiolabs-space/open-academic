<?php

declare(strict_types=1);

namespace App\Services\Feeder;

use App\Exceptions\FeederException;
use App\Models\Feeder\FeederRef;
use App\Models\Kemahasiswaan\AktivitasMahasiswa;

/**
 * Marks which MBKM activities ran under a flagship programme.
 *
 * The mark is what the flagship_mbkm entity claims at PDDIKTI. Programmes
 * come only from the reference pull, so a campus cannot claim one PDDIKTI
 * does not list. Once a claim has been accepted, the mark can be moved to
 * another programme — that is sent as an update — but not removed: the web
 * service offers no withdrawal this sync can rely on, and quietly clearing
 * the column would leave PDDIKTI holding a claim nobody here remembers.
 */
class FlagshipMbkmService
{
    /** @return array<string, string> programme code => name, as PDDIKTI lists them */
    public function pilihan(): array
    {
        return FeederRef::options('program_flagship');
    }

    public function tandai(AktivitasMahasiswa $aktivitas, ?string $kode): AktivitasMahasiswa
    {
        $kode = blank($kode) ? null : $kode;

        if ($kode === null && filled($aktivitas->feeder_flagship_id)) {
            throw FeederException::klaimFlagshipTidakDapatDicabut();
        }

        if ($kode !== null && !FeederRef::query()->type('program_flagship')->where('code', $kode)->exists()) {
            throw FeederException::programFlagshipTidakDikenal($kode);
        }

        $aktivitas->update(['flagship_kode' => $kode]);

        return $aktivitas;
    }
}

<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Why one selected past student would not be claimed.
 *
 * Every selected row is checked before anything is sent, and one problem
 * anywhere stops the whole claim: the operator gets the full list at once,
 * not a run that fails row by row halfway through.
 */
enum MasalahKlaimLampau: string
{
    /** Nobody here to report under the record once it is claimed. */
    case TanpaMahasiswaLokal = 'tanpa_mahasiswa_lokal';

    /** The matcher could not decide, and nobody has said why it should be claimed anyway. */
    case AmbiguTanpaAlasan = 'ambigu_tanpa_alasan';

    /** The listed row lacks a field the claim act is sent. */
    case KunciTidakLengkap = 'kunci_tidak_lengkap';

    /** PDDIKTI no longer lists it, and no claim from here went through. */
    case TidakTerdaftarLagi = 'tidak_terdaftar_lagi';

    /** Another local student already holds this registration. */
    case RegistrasiDipakai = 'registrasi_dipakai';

    /**
     * The problem a row has on what the matcher said alone, or null.
     *
     * Shared by the report, which shows it before anyone selects the row,
     * and by the pre-flight check, which re-reads PDDIKTI and adds what only
     * a fresh read can tell.
     */
    public static function untuk(StatusLampauFeeder $status, bool $adaKunci, bool $adaCatatan): ?self
    {
        return match (true) {
            $status === StatusLampauFeeder::TanpaLokal => self::TanpaMahasiswaLokal,
            !$adaKunci => self::KunciTidakLengkap,
            $status === StatusLampauFeeder::Ambigu && !$adaCatatan => self::AmbiguTanpaAlasan,
            default => null,
        };
    }

    public function label(): string
    {
        return __('feeder.lampau.masalah.'.$this->value);
    }
}

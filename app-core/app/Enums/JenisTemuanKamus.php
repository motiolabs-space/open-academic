<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How what a mapper sends disagrees with the campus Feeder's dictionary.
 *
 * Each kind has its own remedy, and none of them is applied here: the check
 * reports, a person decides whether the mapper, the config, or the reading
 * is wrong.
 */
enum JenisTemuanKamus: string
{
    /** We send it; the dictionary does not list it. Feeder ignores or refuses it. */
    case TidakDikenal = 'tidak_dikenal';

    /** The dictionary requires it; no mapper sends it. Feeder refuses the row. */
    case WajibTidakDikirim = 'wajib_tidak_dikirim';

    /** One of each above that look like the same field under two names. */
    case BedaNama = 'beda_nama';

    /** The act itself could not be read from the dictionary. */
    case AksiTidakTerbaca = 'aksi_tidak_terbaca';

    public function label(): string
    {
        return __('feeder.kamus.jenis.'.$this->value);
    }

    public function tone(): string
    {
        return match ($this) {
            self::WajibTidakDikirim, self::BedaNama => 'danger',
            self::TidakDikenal => 'warning',
            self::AksiTidakTerbaca => 'neutral',
        };
    }
}

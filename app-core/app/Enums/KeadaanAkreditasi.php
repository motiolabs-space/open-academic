<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where a programme's accreditation stands on a given day.
 *
 * Derived, never stored: the same decree is "berlaku" today and
 * "kedaluwarsa" next year without anybody touching it.
 */
enum KeadaanAkreditasi: string
{
    case Berlaku = 'berlaku';
    case SegeraKedaluwarsa = 'segera_kedaluwarsa';
    case Kedaluwarsa = 'kedaluwarsa';
    case TidakTerakreditasi = 'tidak_terakreditasi';
    case BelumDicatat = 'belum_dicatat';

    public function label(): string
    {
        return __('akreditasi.keadaan.'.$this->value);
    }

    public function tone(): string
    {
        return match ($this) {
            self::Berlaku => 'success',
            self::SegeraKedaluwarsa => 'warning',
            self::Kedaluwarsa, self::TidakTerakreditasi => 'danger',
            self::BelumDicatat => 'neutral',
        };
    }

    /** Whether the staff dashboard should raise it. */
    public function perluPerhatian(): bool
    {
        return $this !== self::Berlaku;
    }
}

<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where a payment dispensation stands.
 *
 * "Active" is not a state of its own: a granted dispensation is active until
 * its date passes, and expiring needs nobody to press anything.
 */
enum StatusDispensasi: string
{
    case Diajukan = 'diajukan';
    case Disetujui = 'disetujui';
    case Ditolak = 'ditolak';
    case Dicabut = 'dicabut';

    public function label(): string
    {
        return __('keuangan.dispensasi.status.'.$this->value);
    }

    public function tone(): string
    {
        return match ($this) {
            self::Diajukan => 'warning',
            self::Disetujui => 'success',
            self::Ditolak, self::Dicabut => 'neutral',
        };
    }
}

<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What a row in `akuntansi_pemetaan` points at.
 *
 * Two kinds of master data get an identity on the accounting side: the student,
 * who becomes a customer contact, and the tariff component, which becomes a
 * service product. Everything else is a transaction and has no lasting id worth
 * remembering.
 */
enum JenisEntitasAkuntansi: string
{
    case Mahasiswa = 'mahasiswa';
    case Komponen = 'komponen';

    public function label(): string
    {
        return match ($this) {
            self::Mahasiswa => 'Mahasiswa → Kontak',
            self::Komponen => 'Komponen Tarif → Produk',
        };
    }
}

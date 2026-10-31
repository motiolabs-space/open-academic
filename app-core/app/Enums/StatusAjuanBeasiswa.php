<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where a scholarship application stands.
 *
 * Ditetapkan means an award exists: the application points at the
 * beasiswa_penerima row the finance office created. Nothing else moves money.
 */
enum StatusAjuanBeasiswa: string
{
    case Diajukan = 'diajukan';
    case Ditetapkan = 'ditetapkan';
    case Ditolak = 'ditolak';

    public function label(): string
    {
        return __('swalayan.beasiswa.status.'.$this->value);
    }

    public function tone(): string
    {
        return match ($this) {
            self::Diajukan => 'warning',
            self::Ditetapkan => 'success',
            self::Ditolak => 'danger',
        };
    }
}

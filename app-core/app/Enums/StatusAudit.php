<?php

declare(strict_types=1);

namespace App\Enums;

/** The life of one internal quality audit. */
enum StatusAudit: string
{
    case Direncanakan = 'direncanakan';
    case Berlangsung = 'berlangsung';
    case Selesai = 'selesai';

    public function label(): string
    {
        return match ($this) {
            self::Direncanakan => 'Direncanakan',
            self::Berlangsung => 'Berlangsung',
            self::Selesai => 'Selesai',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Direncanakan => 'neutral',
            self::Berlangsung => 'info',
            self::Selesai => 'success',
        };
    }

    /** Whether findings may still be added or edited. */
    public function menerimaTemuan(): bool
    {
        return $this === self::Berlangsung;
    }
}

<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where a course's outcome set (CPMK and sub-CPMK) has got to.
 *
 * Same life as a teaching plan, and for the same reason: once grade
 * components are mapped to a version in force, editing it would quietly move
 * marks already recorded from one outcome to another. Revising means a new
 * version; the old one is archived, not rewritten (ADR-038).
 */
enum StatusCpmk: string
{
    case Draft = 'draft';
    case Berlaku = 'berlaku';
    case Diarsipkan = 'diarsipkan';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::Berlaku => 'Berlaku',
            self::Diarsipkan => 'Diarsipkan',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Draft => 'neutral',
            self::Berlaku => 'success',
            self::Diarsipkan => 'info',
        };
    }

    public function dapatDisunting(): bool
    {
        return $this === self::Draft;
    }
}

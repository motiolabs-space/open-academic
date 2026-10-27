<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What a read of PDDIKTI's past-student list concluded about one row.
 *
 * The first three come from the adoption matcher, seen from the Feeder row's
 * side. The last is a claim that went through: it is a fact about PDDIKTI,
 * not a finding, so a later read leaves it standing.
 */
enum StatusLampauFeeder: string
{
    /** One local student, matched on NIM and NIK (or on the name where a NIK is missing). */
    case Cocok = 'cocok';

    /** A local student who may be this row, but not one the matcher may decide on. */
    case Ambigu = 'ambigu';

    /** Nobody here: claiming it would bring in a record no local student reports under. */
    case TanpaLokal = 'tanpa_lokal';

    /** Claimed through the web service; adoption takes it from here. */
    case Diklaim = 'diklaim';

    public function label(): string
    {
        return __('feeder.lampau.status.'.$this->value);
    }

    public function tone(): string
    {
        return match ($this) {
            self::Cocok, self::Diklaim => 'success',
            self::Ambigu => 'danger',
            self::TanpaLokal => 'neutral',
        };
    }
}

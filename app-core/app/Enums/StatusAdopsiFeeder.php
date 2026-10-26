<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What one adoption run concluded about one student.
 *
 * The four automatic outcomes mirror reconciliation's: matched, here only,
 * there only, and undecidable. The last two are a person's decisions about
 * an undecidable row, and survive every later run — a run that re-asked the
 * same question would undo an answer someone gave with a reason.
 */
enum StatusAdopsiFeeder: string
{
    /** Matched on NIM and NIK; the Feeder identifiers are now on the student. */
    case Diadopsi = 'diadopsi';

    /** At PDDIKTI, with no local student to give it to. */
    case HanyaFeeder = 'hanya_feeder';

    /** Here, with no candidate at PDDIKTI: a first sync will register them. */
    case HanyaLokal = 'hanya_lokal';

    /** A candidate exists, but not one the matcher may adopt on its own. */
    case Ambigu = 'ambigu';

    /** Linked by a person to one of the candidates, with a reason. */
    case Ditautkan = 'ditautkan';

    /** Declared by a person to be nobody PDDIKTI holds, with a reason. */
    case Dipisahkan = 'dipisahkan';

    public function label(): string
    {
        return __('feeder.adopsi.status.'.$this->value);
    }

    public function tone(): string
    {
        return match ($this) {
            self::Diadopsi, self::Ditautkan => 'success',
            self::HanyaLokal, self::Dipisahkan => 'neutral',
            self::HanyaFeeder => 'warning',
            self::Ambigu => 'danger',
        };
    }

    /** A person's decision, which a later run leaves standing. */
    public function keputusanManual(): bool
    {
        return $this === self::Ditautkan || $this === self::Dipisahkan;
    }
}

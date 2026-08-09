<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What kind of unit this is.
 *
 * Only three, and deliberately coarse. A finer taxonomy (biro / bagian / sub
 * bagian / UPT / lembaga) describes the *level* a unit sits at, which the tree
 * already says — and encoding it twice means the two eventually disagree.
 */
enum JenisUnitKerja: string
{
    /** Rectorate, bureaus, sections — the administrative spine. */
    case Struktural = 'struktural';

    /** Faculties, departments, study programmes. */
    case Akademik = 'akademik';

    /** Libraries, laboratories, IT — units that serve the other two. */
    case Penunjang = 'penunjang';

    public function label(): string
    {
        return match ($this) {
            self::Struktural => 'Struktural',
            self::Akademik => 'Akademik',
            self::Penunjang => 'Penunjang',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $j): array => [$j->value => $j->label()])
            ->all();
    }
}

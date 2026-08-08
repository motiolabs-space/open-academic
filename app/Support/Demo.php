<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\System\Setting;

/**
 * Whether this database holds demo data, and nothing more.
 *
 * The marker exists for one reason: `openacademic:demo-hapus` wipes every table,
 * and it must be impossible to point at a real campus. Refusing when the marker
 * is absent means the destructive command can only ever destroy something this
 * application put there itself.
 *
 * Written by DemoCampusSeeder rather than by the install command, so it covers
 * every path that seeds demo data — including `migrate:fresh --seed`.
 */
class Demo
{
    public const GRUP = 'demo';

    public const KUNCI = 'dipasang_pada';

    /** Records that this database now contains demo data. */
    public static function tandai(): void
    {
        Setting::put(self::GRUP, self::KUNCI, now()->toIso8601String());
    }

    public static function terpasang(): bool
    {
        return filled(self::dipasangPada());
    }

    public static function dipasangPada(): ?string
    {
        $nilai = Setting::get(self::GRUP, self::KUNCI);

        return blank($nilai) ? null : (string) $nilai;
    }
}

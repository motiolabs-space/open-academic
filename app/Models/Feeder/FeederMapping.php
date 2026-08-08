<?php

declare(strict_types=1);

namespace App\Models\Feeder;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Translation between an Open Academic enum value and the Feeder code for the
 * same concept.
 *
 * Local enums are never assumed to match PDDIKTI codes — an institution may
 * run an older Feeder build with a different code set, and this table is what
 * absorbs that difference without a code change.
 */
class FeederMapping extends Model
{
    protected $table = 'feeder_mappings';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected static function booted(): void
    {
        static::saved(fn (self $m) => Cache::forget("feeder.map.{$m->group}"));
        static::deleted(fn (self $m) => Cache::forget("feeder.map.{$m->group}"));
    }

    /** Feeder code for a local value, or null when unmapped. */
    public static function toFeeder(string $group, string $localValue): ?string
    {
        return self::map($group)[$localValue] ?? null;
    }

    /** Local value for a Feeder code, or null when unmapped. */
    public static function toLocal(string $group, string $feederCode): ?string
    {
        return array_search($feederCode, self::map($group), true) ?: null;
    }

    /** @return array<string, string> */
    private static function map(string $group): array
    {
        return Cache::rememberForever(
            "feeder.map.{$group}",
            fn (): array => static::query()
                ->where('group', $group)
                ->pluck('feeder_code', 'local_value')
                ->all(),
        );
    }
}

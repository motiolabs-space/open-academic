<?php

declare(strict_types=1);

namespace App\Models\System;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Runtime configuration an institution may change from the admin UI without
 * editing .env — branding, calendar toggles, grading overrides.
 *
 * Reads are cached; every write busts the cache for that group.
 */
class Setting extends Model
{
    protected $table = 'settings';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected static function booted(): void
    {
        static::saved(fn (self $setting) => Cache::forget(self::cacheKey($setting->group)));
        static::deleted(fn (self $setting) => Cache::forget(self::cacheKey($setting->group)));
    }

    /** @return array<string, mixed> */
    public static function group(string $group): array
    {
        return Cache::rememberForever(
            self::cacheKey($group),
            fn (): array => static::query()
                ->where('group', $group)
                ->get()
                ->mapWithKeys(fn (self $row): array => [$row->key => $row->castedValue()])
                ->all(),
        );
    }

    public static function get(string $group, string $key, mixed $default = null): mixed
    {
        return self::group($group)[$key] ?? $default;
    }

    public static function put(string $group, string $key, mixed $value, string $type = 'string'): void
    {
        static::query()->updateOrCreate(
            ['group' => $group, 'key' => $key],
            ['value' => $type === 'json' ? json_encode($value) : (string) $value, 'type' => $type],
        );
    }

    private function castedValue(): mixed
    {
        return match ($this->type) {
            'int' => (int) $this->value,
            'bool' => filter_var($this->value, FILTER_VALIDATE_BOOL),
            'json' => json_decode((string) $this->value, true),
            default => $this->value,
        };
    }

    private static function cacheKey(string $group): string
    {
        return "settings.{$group}";
    }
}

<?php

declare(strict_types=1);

namespace App\Models\Feeder;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Reference data pulled from Neo Feeder (agama, wilayah, jenjang, status
 * codes). Feeder is authoritative for these; a sync run always pulls
 * references before it pushes anything.
 */
class FeederRef extends Model
{
    protected $table = 'feeder_refs';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'synced_at' => 'datetime',
        ];
    }

    /** @param Builder<self> $query */
    public function scopeType(Builder $query, string $refType): Builder
    {
        return $query->where('ref_type', $refType);
    }

    /** @return array<string, string> code => name */
    public static function options(string $refType): array
    {
        return static::query()->type($refType)->pluck('name', 'code')->all();
    }
}

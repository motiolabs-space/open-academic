<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Gives a model a stable public identifier.
 *
 * Rows keep their auto-increment `id` for joins, but anything that leaves the
 * application — a URL, an API resource, a webhook payload — refers to the
 * `uuid`. That way enrolment counts and record ordering are never leaked, and
 * Campus Bridge consumers hold identifiers that survive a database reseed.
 *
 * @mixin Model
 */
trait HasUuid
{
    public static function bootHasUuid(): void
    {
        static::creating(function (Model $model): void {
            if (blank($model->getAttribute('uuid'))) {
                $model->setAttribute('uuid', (string) Str::uuid());
            }
        });
    }

    /** Bind {model} route parameters by uuid instead of id. */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function scopeWhereUuid(Builder $query, string $uuid): Builder
    {
        return $query->where($this->getTable().'.uuid', $uuid);
    }
}

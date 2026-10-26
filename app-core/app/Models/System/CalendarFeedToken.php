<?php

declare(strict_types=1);

namespace App\Models\System;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * One secret iCalendar feed URL.
 *
 * Holds the hash of the token, never the token. See the migration.
 *
 * @property ?Carbon $last_used_at
 * @property ?Carbon $revoked_at
 */
class CalendarFeedToken extends Model
{
    protected $table = 'calendar_feed_tokens';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->whereNull('revoked_at');
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}

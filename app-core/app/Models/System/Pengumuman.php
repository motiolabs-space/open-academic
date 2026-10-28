<?php

declare(strict_types=1);

namespace App\Models\System;

use App\Enums\UserRole;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A portal announcement.
 *
 * Deliberately minimal. Anything richer — comments, reactions, a feed — is
 * Open Campus territory; do not grow this into a CMS.
 */
class Pengumuman extends Model
{
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'pengumuman';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'target_roles' => 'array',
            'is_pinned' => 'boolean',
            'published_at' => 'datetime',
            'berakhir_at' => 'datetime',
        ];
    }

    /** @param Builder<self> $query */
    public function scopeTerbit(Builder $query): Builder
    {
        return $query->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    /** @param Builder<self> $query */
    public function scopeUntuk(Builder $query, string $role): Builder
    {
        return $query->whereJsonContains('target_roles', $role);
    }

    /**
     * Not yet past its end, or without one.
     *
     * @param Builder<self> $query
     */
    public function scopeBelumBerakhir(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereNull('berakhir_at')->orWhere('berakhir_at', '>', now()));
    }

    /**
     * What one portal shows: published, not yet ended, aimed at that portal,
     * pinned first and then newest.
     *
     * The one definition all three portals read — dashboards and the list
     * pages alike — so "why does the lecturer portal show this and the student
     * portal not" always has the same answer.
     *
     * @param Builder<self> $query
     */
    public function scopeTampilUntuk(Builder $query, UserRole $peran): Builder
    {
        return $query->terbit()
            ->belumBerakhir()
            ->untuk($peran->value)
            ->orderByDesc('is_pinned')
            ->orderByDesc('published_at')
            ->orderByDesc('id');
    }
}

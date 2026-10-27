<?php

declare(strict_types=1);

namespace App\Traits;

use App\Enums\StatusRiwayat;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\Staff;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The review columns every personnel-history table carries.
 *
 * A lecturer files; a staff member vouches. Both are recorded, separately,
 * because a SISTER submission or a promotion file reads the second and an
 * audit asks about the first.
 *
 * @property StatusRiwayat $status
 *
 * @mixin Model
 */
trait HasValidasiRiwayat
{
    public function initializeHasValidasiRiwayat(): void
    {
        $this->mergeCasts([
            'status' => StatusRiwayat::class,
            'divalidasi_at' => 'datetime',
        ]);
    }

    public function dosen(): BelongsTo
    {
        return $this->belongsTo(Dosen::class);
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'divalidasi_oleh');
    }

    /**
     * Rows a staff member has approved — what every export and every rule reads.
     *
     * @param Builder<static> $query
     */
    public function scopeDisetujui(Builder $query): Builder
    {
        return $query->where($this->getTable().'.status', StatusRiwayat::Disetujui->value);
    }

    /** @param Builder<static> $query */
    public function scopeMenungguValidasi(Builder $query): Builder
    {
        return $query->where($this->getTable().'.status', StatusRiwayat::Diajukan->value);
    }

    /**
     * Not named disetujui(): a scope and an instance method may not share a
     * name — PHP resolves Model::disetujui() to the instance method and fails,
     * the collision already paid for in JabatanFungsionalDosen and Rps.
     */
    public function sudahDisetujui(): bool
    {
        return $this->status === StatusRiwayat::Disetujui;
    }
}

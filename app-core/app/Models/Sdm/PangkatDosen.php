<?php

declare(strict_types=1);

namespace App\Models\Sdm;

use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One rung of a lecturer's civil-service rank history.
 *
 * At most one row per lecturer is current, enforced by the nullable-unique
 * `dosen_aktif_id` rather than a boolean — see the migration.
 */
class PangkatDosen extends Model
{
    use HasFactory;
    use HasLogAktivitas;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'pangkat_dosen';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return ['tmt' => 'date', 'tanggal_sk' => 'date'];
    }

    public function dosen(): BelongsTo
    {
        return $this->belongsTo(Dosen::class);
    }

    /**
     * Currently held ranks.
     *
     * Named scopeAktif, not scopeBerlaku: a scope and an instance method may
     * not share a name — PHP resolves Model::berlaku() to the instance method
     * and fails. That collision has been paid for twice in this repo
     * (JabatanFungsionalDosen, Rps).
     */
    public function scopeAktif(Builder $query): Builder
    {
        return $query->whereNotNull('dosen_aktif_id');
    }

    public function berlaku(): bool
    {
        return $this->dosen_aktif_id !== null;
    }
}

<?php

declare(strict_types=1);

namespace App\Models\Keuangan;

use App\Models\Akademik\Prodi;
use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One cell of the fee matrix.
 *
 * A row matches a student when every dimension it declares (prodi, intake
 * year, admission path, UKT band) either matches or is left null as a
 * wildcard. The most specific matching row wins, so a general fallback and a
 * programme-specific override can coexist.
 */
class Tarif extends Model
{
    use HasFactory;
    use HasLogAktivitas;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'tarif';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'nominal' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function prodi(): BelongsTo
    {
        return $this->belongsTo(Prodi::class);
    }

    /** Number of declared (non-wildcard) dimensions — higher wins a tie. */
    public function spesifisitas(): int
    {
        return collect([$this->prodi_id, $this->angkatan, $this->jalur_masuk, $this->golongan_ukt])
            ->filter()
            ->count();
    }

    /** @param Builder<self> $query */
    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** @param Builder<self> $query */
    public function scopeBerlakuPada(Builder $query, string $termCode): Builder
    {
        return $query
            ->where(fn (Builder $q) => $q->whereNull('term_berlaku_dari')->orWhere('term_berlaku_dari', '<=', $termCode))
            ->where(fn (Builder $q) => $q->whereNull('term_berlaku_sampai')->orWhere('term_berlaku_sampai', '>=', $termCode));
    }
}

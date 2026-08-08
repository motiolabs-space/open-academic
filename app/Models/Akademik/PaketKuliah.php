<?php

declare(strict_types=1);

namespace App\Models\Akademik;

use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** A fixed study plan issued to a cohort for one semester. */
class PaketKuliah extends Model
{
    use HasFactory;
    use HasLogAktivitas;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'paket_kuliah';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'semester_ke' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function kurikulum(): BelongsTo
    {
        return $this->belongsTo(Kurikulum::class);
    }

    public function konsentrasi(): BelongsTo
    {
        return $this->belongsTo(Konsentrasi::class);
    }

    public function mataKuliah(): BelongsToMany
    {
        return $this->belongsToMany(MataKuliah::class, 'paket_kuliah_detail', 'paket_kuliah_id', 'mata_kuliah_id')
            ->withTimestamps();
    }

    public function totalSks(): int
    {
        return (int) $this->mataKuliah->sum('sks');
    }

    /** @param  Builder<self>  $query */
    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * The package a student in a given semester receives.
     *
     * A track-specific package wins over the shared one — that is the point of
     * having tracks — and a curriculum with no track packages still works,
     * because most programmes have none.
     */
    public static function untuk(int $kurikulumId, ?int $konsentrasiId, int $semesterKe): ?self
    {
        return static::query()
            ->aktif()
            ->where('kurikulum_id', $kurikulumId)
            ->where('semester_ke', $semesterKe)
            ->where(fn (Builder $q) => $q
                ->where('konsentrasi_id', $konsentrasiId)
                ->orWhereNull('konsentrasi_id'))

            // Track package first: NULL sorts last under this ordering on both
            // MySQL and PostgreSQL because a non-null id is always greater than
            // nothing to compare — so the explicit ordering is written out.
            ->orderByRaw('CASE WHEN konsentrasi_id IS NULL THEN 1 ELSE 0 END')
            ->first();
    }
}

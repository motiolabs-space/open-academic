<?php

declare(strict_types=1);

namespace App\Models\Sdm;

use App\Enums\JenisUnitKerja;
use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

/**
 * One node of the org chart.
 *
 * Replaces the free-text `staff.unit`, where "BAAK", "Baak" and "Bag. Akademik"
 * were three different offices as far as any report was concerned.
 */
class UnitKerja extends Model
{
    use HasFactory;
    use HasLogAktivitas;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'unit_kerja';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    /** Column defaults mirrored so a freshly created model agrees with the row. */
    protected $attributes = [
        'jenis' => JenisUnitKerja::Struktural->value,
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'jenis' => JenisUnitKerja::class,
            'is_active' => 'boolean',
        ];
    }

    public function induk(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function anak(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function staf(): HasMany
    {
        return $this->hasMany(Staff::class, 'unit_kerja_id');
    }

    public function kepalaStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'kepala_staff_id');
    }

    public function kepalaDosen(): BelongsTo
    {
        return $this->belongsTo(Dosen::class, 'kepala_dosen_id');
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Whoever heads this unit, whichever table they live in.
     *
     * At most one is ever set — UnitKerjaService refuses both.
     */
    public function kepala(): Staff|Dosen|null
    {
        return $this->kepalaStaff ?? $this->kepalaDosen;
    }

    /**
     * This unit and everything under it, from a tree already in memory.
     *
     * Takes the whole set rather than querying, because the callers that need
     * a subtree — a report rolled up by unit, a delegation check — are the ones
     * that would otherwise issue a query per level.
     *
     * @param Collection<int, self> $semua
     * @return Collection<int, self>
     */
    public function turunan(Collection $semua): Collection
    {
        $hasil = collect([$this]);
        $lapis = collect([$this->id]);

        // Bounded by the number of nodes: a cycle is refused at write time, but
        // a loop that trusts the data to be acyclic is one bad row from hanging
        // the request that reads it.
        for ($i = 0; $i < $semua->count() && $lapis->isNotEmpty(); $i++) {
            $anak = $semua->whereIn('parent_id', $lapis->all());

            if ($anak->isEmpty()) {
                break;
            }

            $hasil = $hasil->concat($anak->all());
            $lapis = $anak->pluck('id');
        }

        return $hasil->unique('id')->values();
    }

    /** "Rektorat › Biro Akademik › BAAK" */
    public function jalur(Collection $semua): string
    {
        $bagian = [$this->nama];
        $kini = $this;

        for ($i = 0; $i < $semua->count(); $i++) {
            $induk = $kini->parent_id === null ? null : $semua->firstWhere('id', $kini->parent_id);

            if ($induk === null) {
                break;
            }

            array_unshift($bagian, $induk->nama);
            $kini = $induk;
        }

        return implode(' › ', $bagian);
    }
}

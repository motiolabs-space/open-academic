<?php

declare(strict_types=1);

namespace App\Models\Akademik;

use App\Traits\DapatDicari;
use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Database\Factories\MataKuliahFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A course.
 *
 * Credits are stored split into theory / practice / field practice because
 * that is how PDDIKTI reports them; `sks` is the total kept alongside for
 * queries and is maintained by the same service that writes the parts.
 */
class MataKuliah extends Model
{
    use DapatDicari;

    /** @use HasFactory<MataKuliahFactory> */
    use HasFactory;

    use HasLogAktivitas;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'mata_kuliah';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function prodi(): BelongsTo
    {
        return $this->belongsTo(Prodi::class);
    }

    public function kurikulum(): BelongsToMany
    {
        return $this->belongsToMany(Kurikulum::class, 'kurikulum_mata_kuliah')
            ->withPivot(['semester', 'jenis'])
            ->withTimestamps();
    }

    /** Courses that must be passed before this one may be taken. */
    public function prasyarat(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'mata_kuliah_prasyarat', 'mata_kuliah_id', 'prasyarat_id')
            ->withPivot('jenis')
            ->withTimestamps();
    }

    /** Courses that declare this one as their prerequisite. */
    public function menjadiPrasyaratUntuk(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'mata_kuliah_prasyarat', 'prasyarat_id', 'mata_kuliah_id')
            ->withPivot('jenis')
            ->withTimestamps();
    }

    public function kelasKuliah(): HasMany
    {
        return $this->hasMany(KelasKuliah::class);
    }

    /** @param Builder<self> $query */
    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    protected static function newFactory(): MataKuliahFactory
    {
        return MataKuliahFactory::new();
    }
}

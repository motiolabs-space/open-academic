<?php

declare(strict_types=1);

namespace App\Models\Akademik;

use App\Models\Kemahasiswaan\Mahasiswa;
use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** One track within a curriculum. */
class Konsentrasi extends Model
{
    use HasFactory;
    use HasLogAktivitas;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'konsentrasi';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'sks_wajib' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function kurikulum(): BelongsTo
    {
        return $this->belongsTo(Kurikulum::class);
    }

    /** Courses belonging to this track alone. */
    public function mataKuliah(): BelongsToMany
    {
        return $this->belongsToMany(MataKuliah::class, 'kurikulum_mata_kuliah', 'konsentrasi_id', 'mata_kuliah_id')
            ->withPivot(['kurikulum_id', 'semester', 'jenis']);
    }

    public function mahasiswa(): HasMany
    {
        return $this->hasMany(Mahasiswa::class);
    }

    /** @param  Builder<self>  $query */
    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}

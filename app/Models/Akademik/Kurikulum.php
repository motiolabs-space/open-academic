<?php

declare(strict_types=1);

namespace App\Models\Akademik;

use App\Models\Kemahasiswaan\Mahasiswa;
use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Database\Factories\KurikulumFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A versioned curriculum.
 *
 * A new version never overwrites its predecessor: students stay bound to the
 * version they enrolled under, so a 2022 intake still graduates against the
 * 2022 requirements even after a 2026 revision is published.
 */
class Kurikulum extends Model
{
    /** @use HasFactory<KurikulumFactory> */
    use HasFactory;

    use HasLogAktivitas;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'kurikulum';

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

    /**
     * Courses in this version. The recommended semester and whether the course
     * is compulsory live on the pivot, because both differ between versions.
     */
    public function mataKuliah(): BelongsToMany
    {
        return $this->belongsToMany(MataKuliah::class, 'kurikulum_mata_kuliah')
            ->withPivot(['semester', 'jenis'])
            ->withTimestamps()
            ->orderByPivot('semester');
    }

    /**
     * Students bound to this version.
     *
     * A student keeps the curriculum they entered under for their whole degree,
     * so this is also the answer to "may this version be deleted" — it may not,
     * as long as anybody's transcript still refers to it.
     */
    public function mahasiswa(): HasMany
    {
        return $this->hasMany(Mahasiswa::class);
    }

    /** Courses a student in the given study semester is expected to take. */
    public function mataKuliahSemester(int $semester): BelongsToMany
    {
        return $this->mataKuliah()->wherePivot('semester', $semester);
    }

    protected static function newFactory(): KurikulumFactory
    {
        return KurikulumFactory::new();
    }
}

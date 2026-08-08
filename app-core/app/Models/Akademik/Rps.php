<?php

declare(strict_types=1);

namespace App\Models\Akademik;

use App\Enums\StatusRps;
use App\Models\Sdm\Dosen;
use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One course's teaching plan for one term.
 *
 * @property StatusRps $status
 */
class Rps extends Model
{
    use HasFactory;
    use HasLogAktivitas;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'rps';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'status' => StatusRps::class,
            'versi' => 'integer',
            'disahkan_at' => 'datetime',
        ];
    }

    public function mataKuliah(): BelongsTo
    {
        return $this->belongsTo(MataKuliah::class);
    }

    public function tahunAkademik(): BelongsTo
    {
        return $this->belongsTo(TahunAkademik::class);
    }

    public function pertemuan(): HasMany
    {
        return $this->hasMany(RpsPertemuan::class)->orderBy('pertemuan_ke');
    }

    /** Programme outcomes this course answers for. */
    public function cpl(): BelongsToMany
    {
        return $this->belongsToMany(ProdiCpl::class, 'rps_cpl', 'rps_id', 'prodi_cpl_id')
            ->withPivot('rumusan')
            ->withTimestamps();
    }

    public function penyusun(): BelongsTo
    {
        return $this->belongsTo(Dosen::class, 'disusun_by_dosen_id');
    }

    public function pengesah(): BelongsTo
    {
        return $this->belongsTo(Dosen::class, 'disahkan_by_dosen_id');
    }

    public function berlaku(): bool
    {
        return $this->status === StatusRps::Berlaku;
    }

    /** Total planned assessment weight — must reach 100 before publishing. */
    public function totalBobot(): int
    {
        return (int) $this->pertemuan->sum('bobot');
    }

    /**
     * Plans currently in force.
     *
     * Named `aktif` rather than `berlaku` because a scope and an instance method
     * cannot share a name — PHP resolves `Rps::berlaku()` to the instance method
     * and fails. Same collision as JabatanFungsionalDosen, and the same fix.
     *
     * @param Builder<self> $query
     */
    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('status', StatusRps::Berlaku->value);
    }

    /** The plan in force for a course in a term, if one has been published. */
    public static function untuk(int $mataKuliahId, int $tahunAkademikId): ?self
    {
        return static::query()
            ->where('mata_kuliah_id', $mataKuliahId)
            ->where('tahun_akademik_id', $tahunAkademikId)
            ->aktif()
            ->first();
    }
}

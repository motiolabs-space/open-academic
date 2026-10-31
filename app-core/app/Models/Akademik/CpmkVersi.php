<?php

declare(strict_types=1);

namespace App\Models\Akademik;

use App\Enums\StatusCpmk;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\Staff;
use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One version of a course's outcome set within one curriculum.
 *
 * @property StatusCpmk $status
 */
class CpmkVersi extends Model
{
    use HasLogAktivitas;
    use HasUuid;
    use SoftDeletes;

    /** The relations every screen of this module reads. */
    public const MUAT = ['cpmk.sub', 'cpmk.cpl'];

    protected $table = 'cpmk_versi';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'status' => StatusCpmk::class,
            'versi' => 'integer',
            'diterbitkan_at' => 'datetime',
        ];
    }

    public function kurikulum(): BelongsTo
    {
        return $this->belongsTo(Kurikulum::class);
    }

    public function mataKuliah(): BelongsTo
    {
        return $this->belongsTo(MataKuliah::class);
    }

    public function cpmk(): HasMany
    {
        return $this->hasMany(Cpmk::class)->orderBy('urutan')->orderBy('kode');
    }

    public function penyusunStaf(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'disusun_by_staff_id');
    }

    public function penyusunDosen(): BelongsTo
    {
        return $this->belongsTo(Dosen::class, 'disusun_by_dosen_id');
    }

    public function berlaku(): bool
    {
        return $this->status === StatusCpmk::Berlaku;
    }

    /**
     * Versions in force. Named `aktif` because a scope and the instance method
     * `berlaku()` cannot share a name — the same collision as Rps.
     *
     * @param Builder<self> $query
     */
    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('status', StatusCpmk::Berlaku->value);
    }
}

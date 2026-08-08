<?php

declare(strict_types=1);

namespace App\Models\Akademik;

use App\Enums\KrsStatus;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Dosen;
use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Database\Factories\KrsFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A study plan (Kartu Rencana Studi) for one student in one term.
 *
 * Status transitions are events, not attribute writes: KrsService is the only
 * legitimate mutator and every move is audited. `batas_sks` and `ips_acuan`
 * are snapshotted at creation so a later grade correction can never
 * retroactively invalidate a plan that was already approved.
 */
class Krs extends Model
{
    /** @use HasFactory<KrsFactory> */
    use HasFactory;

    use HasLogAktivitas;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'krs';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'status' => KrsStatus::class,
            'diajukan_at' => 'datetime',
            'disetujui_at' => 'datetime',
            'feeder_synced_at' => 'datetime',
        ];
    }

    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(Mahasiswa::class);
    }

    public function tahunAkademik(): BelongsTo
    {
        return $this->belongsTo(TahunAkademik::class);
    }

    public function detail(): HasMany
    {
        return $this->hasMany(KrsDetail::class);
    }

    /** The academic advisor who approved or rejected the plan. */
    public function penyetuju(): BelongsTo
    {
        return $this->belongsTo(Dosen::class, 'disetujui_by_dosen_id');
    }

    public function sisaSks(): int
    {
        return max(0, $this->batas_sks - $this->total_sks);
    }

    /** @param Builder<self> $query */
    public function scopeMenungguPersetujuan(Builder $query): Builder
    {
        return $query->where('status', KrsStatus::Diajukan->value);
    }

    /** Plans awaiting approval from one particular academic advisor. */
    /** @param Builder<self> $query */
    public function scopeUntukWali(Builder $query, int $dosenId): Builder
    {
        return $query->whereHas(
            'mahasiswa',
            fn (Builder $mahasiswa) => $mahasiswa->where('dosen_wali_id', $dosenId),
        );
    }

    protected static function newFactory(): KrsFactory
    {
        return KrsFactory::new();
    }
}

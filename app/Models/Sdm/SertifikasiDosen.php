<?php

declare(strict_types=1);

namespace App\Models\Sdm;

use App\Enums\JenisSertifikasi;
use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A certificate held by a lecturer.
 *
 * @property JenisSertifikasi $jenis
 */
class SertifikasiDosen extends Model
{
    use HasFactory;
    use HasLogAktivitas;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'sertifikasi_dosen';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'jenis' => JenisSertifikasi::class,
            'tanggal' => 'date',
            'berlaku_sampai' => 'date',
        ];
    }

    public function dosen(): BelongsTo
    {
        return $this->belongsTo(Dosen::class);
    }

    public function berlaku(): bool
    {
        return $this->berlaku_sampai === null
            || !$this->berlaku_sampai->copy()->endOfDay()->isPast();
    }

    /**
     * @param Builder<self> $query
     */
    public function scopeSerdos(Builder $query): Builder
    {
        return $query->where('jenis', JenisSertifikasi::Serdos->value);
    }
}

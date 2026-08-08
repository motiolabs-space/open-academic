<?php

declare(strict_types=1);

namespace App\Models\Kemahasiswaan;

use App\Enums\StudentStatus;
use App\Models\Akademik\TahunAkademik;
use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The per-term enrolment record.
 *
 * This is the local shape of the PDDIKTI "AktivitasKuliahMahasiswa" payload,
 * and it doubles as the KHS header: once `is_final` is set, ips/ipk/sks for
 * that term are frozen and only an audited correction may touch them.
 */
class StatusMahasiswa extends Model
{
    use HasFactory;
    use HasLogAktivitas;
    use HasUuid;

    protected $table = 'status_mahasiswa';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'status' => StudentStatus::class,
            'ips' => 'decimal:2',
            'ipk' => 'decimal:2',
            'is_final' => 'boolean',
            'finalized_at' => 'datetime',
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

    /** @param Builder<self> $query */
    public function scopeTerm(Builder $query, int $tahunAkademikId): Builder
    {
        return $query->where('tahun_akademik_id', $tahunAkademikId);
    }
}

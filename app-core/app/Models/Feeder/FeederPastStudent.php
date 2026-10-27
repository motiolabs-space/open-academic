<?php

declare(strict_types=1);

namespace App\Models\Feeder;

use App\Enums\AlasanAmbiguAdopsi;
use App\Enums\StatusLampauFeeder;
use App\Models\Akademik\Prodi;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Staff;
use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row of PDDIKTI's past-student list, as last read, or a claim of one.
 *
 * Findings are written in bulk and replaced by the next read. A claim is an
 * update to one row, which the activity log records with the operator — the
 * part of claiming a person decides, and the part that has to answer "who
 * brought this record in, and why?".
 */
class FeederPastStudent extends Model
{
    use HasLogAktivitas;
    use HasUuid;

    protected $table = 'feeder_past_students';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'status' => StatusLampauFeeder::class,
            'alasan' => AlasanAmbiguAdopsi::class,
            'tanggal_lahir_feeder' => 'date',
            'nik_cocok' => 'boolean',
            'kunci_klaim' => 'array',
            'diklaim_at' => 'datetime',
        ];
    }

    public function prodi(): BelongsTo
    {
        return $this->belongsTo(Prodi::class);
    }

    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(Mahasiswa::class);
    }

    public function diklaimOleh(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'diklaim_by_staff_id');
    }

    /** @param Builder<self> $query */
    public function scopeStatus(Builder $query, StatusLampauFeeder $status): Builder
    {
        return $query->where('status', $status->value);
    }

    /** @param Builder<self> $query */
    public function scopeUntuk(Builder $query, int $prodiId, string $periode): Builder
    {
        return $query->where('prodi_id', $prodiId)->where('periode', $periode);
    }
}

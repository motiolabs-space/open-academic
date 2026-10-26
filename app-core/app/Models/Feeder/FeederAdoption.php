<?php

declare(strict_types=1);

namespace App\Models\Feeder;

use App\Enums\AlasanAmbiguAdopsi;
use App\Enums\StatusAdopsiFeeder;
use App\Models\Akademik\Prodi;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Staff;
use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One student's outcome in an adoption run, or a person's decision about one.
 *
 * Automatic rows are written in bulk and replaced by the next run, like
 * reconciliation findings. A link or a separation made by hand is an
 * update to one row, which the activity log records with the actor and the
 * reason — the only part of adoption a person decides, and so the part that
 * has to answer "who said these two were the same student?".
 */
class FeederAdoption extends Model
{
    use HasLogAktivitas;
    use HasUuid;

    protected $table = 'feeder_adoptions';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'status' => StatusAdopsiFeeder::class,
            'alasan' => AlasanAmbiguAdopsi::class,
            'kandidat' => 'array',
            'diputuskan_at' => 'datetime',
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

    public function diputuskanOleh(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'diputuskan_by_staff_id');
    }

    /**
     * One candidate by its registration identifier, as stored on this row.
     *
     * @return array<string, mixed>|null
     */
    public function kandidatDengan(string $idRegistrasi): ?array
    {
        return collect($this->kandidat ?? [])
            ->first(fn (array $k): bool => ($k['id_registrasi_mahasiswa'] ?? null) === $idRegistrasi);
    }

    /** @param Builder<self> $query */
    public function scopeStatus(Builder $query, StatusAdopsiFeeder $status): Builder
    {
        return $query->where('status', $status->value);
    }

    /** @param Builder<self> $query */
    public function scopeUntuk(Builder $query, int $prodiId, string $periode): Builder
    {
        return $query->where('prodi_id', $prodiId)->where('periode', $periode);
    }
}

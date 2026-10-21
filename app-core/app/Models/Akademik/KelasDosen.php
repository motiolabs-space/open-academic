<?php

declare(strict_types=1);

namespace App\Models\Akademik;

use App\Models\Sdm\Dosen;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One lecturer teaching one class — the `kelas_dosen` row.
 *
 * Until now this table was only ever reached as a pivot through
 * KelasKuliah::dosen(). It gets a model of its own because it is a PDDIKTI
 * entity in its own right (Dosen Pengajar Kelas Kuliah): the row that counts
 * towards the lecturer–student ratio, splits teaching credit under team
 * teaching, and is what SISTER later reads back as "pengajaran". The sync
 * ledger needs a model with a primary key and a `feeder_id` to track it.
 *
 * The pivot relation on KelasKuliah is untouched; this model reads the same
 * rows and writes nothing but the Feeder identifier.
 */
class KelasDosen extends Model
{
    protected $table = 'kelas_dosen';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'porsi_sks' => 'decimal:2',
            'feeder_synced_at' => 'datetime',
        ];
    }

    public function kelasKuliah(): BelongsTo
    {
        return $this->belongsTo(KelasKuliah::class);
    }

    public function dosen(): BelongsTo
    {
        return $this->belongsTo(Dosen::class);
    }

    /** @param Builder<self> $query */
    public function scopeTerm(Builder $query, string $termCode): Builder
    {
        return $query->whereHas('kelasKuliah.tahunAkademik', fn (Builder $q) => $q->where('kode', $termCode));
    }
}

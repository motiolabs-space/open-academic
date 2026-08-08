<?php

declare(strict_types=1);

namespace App\Models\Akademik;

use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A weighted assessment component of a class (Tugas, UTS, UAS, ...).
 * The weights of all components in a class must total 100 — PenilaianService
 * enforces that invariant, the schema only stores the numbers.
 */
class KomponenNilai extends Model
{
    use HasFactory;
    use HasLogAktivitas;
    use HasUuid;

    protected $table = 'komponen_nilai';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    public function kelasKuliah(): BelongsTo
    {
        return $this->belongsTo(KelasKuliah::class);
    }

    public function nilai(): HasMany
    {
        return $this->hasMany(NilaiKomponen::class);
    }
}

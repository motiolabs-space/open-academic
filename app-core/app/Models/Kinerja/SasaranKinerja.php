<?php

declare(strict_types=1);

namespace App\Models\Kinerja;

use App\Models\Sdm\UnitKerja;
use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An objective, owned by a unit rather than a person.
 *
 * A dean serves four years. Were the objective owned by the individual, the
 * faculty's objective would follow the outgoing dean and their successor would
 * start from nothing. The person accountable is derived from whoever heads the
 * unit at the time.
 */
class SasaranKinerja extends Model
{
    use HasFactory;
    use HasLogAktivitas;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'sasaran_kinerja';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    public function periode(): BelongsTo
    {
        return $this->belongsTo(PeriodeKinerja::class, 'periode_kinerja_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(UnitKerja::class, 'unit_kerja_id');
    }

    public function induk(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function anak(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function ukuran(): HasMany
    {
        return $this->hasMany(UkuranKinerja::class);
    }

    /** Whoever heads the owning unit right now — derived, never stored. */
    public function penanggungJawab(): ?object
    {
        return $this->unit?->kepala();
    }
}

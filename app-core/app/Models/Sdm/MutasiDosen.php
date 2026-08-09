<?php

declare(strict_types=1);

namespace App\Models\Sdm;

use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** A posting: arrival, transfer between units, or departure. */
class MutasiDosen extends Model
{
    use HasFactory;
    use HasLogAktivitas;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'mutasi_dosen';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return ['tmt' => 'date'];
    }

    public function dosen(): BelongsTo
    {
        return $this->belongsTo(Dosen::class);
    }

    public function unitAsal(): BelongsTo
    {
        return $this->belongsTo(UnitKerja::class, 'unit_asal_id');
    }

    public function unitTujuan(): BelongsTo
    {
        return $this->belongsTo(UnitKerja::class, 'unit_tujuan_id');
    }
}

<?php

declare(strict_types=1);

namespace App\Models\Akademik;

use App\Traits\HasLogAktivitas;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What share of a grade component measures which CPMK or sub-CPMK.
 *
 * A model rather than a bare pivot because the target is one of two levels,
 * and because remapping an exam changes how marks are read — it is audited
 * like the marks themselves.
 */
class KomponenNilaiCpmk extends Model
{
    use HasLogAktivitas;

    protected $table = 'komponen_nilai_cpmk';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return ['porsi' => 'integer'];
    }

    public function komponen(): BelongsTo
    {
        return $this->belongsTo(KomponenNilai::class, 'komponen_nilai_id');
    }

    public function cpmk(): BelongsTo
    {
        return $this->belongsTo(Cpmk::class);
    }

    public function subCpmk(): BelongsTo
    {
        return $this->belongsTo(SubCpmk::class);
    }

    /** "s:12" for a sub-CPMK, "c:4" for a CPMK without parts. */
    public function sasaran(): string
    {
        return $this->sub_cpmk_id !== null ? 's:'.$this->sub_cpmk_id : 'c:'.$this->cpmk_id;
    }
}

<?php

declare(strict_types=1);

namespace App\Models\Sdm;

use App\Enums\UnsurBkd;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One frozen line of a submitted report.
 *
 * Values are copies, not references. `penugasan` is provenance only — nothing
 * reads through it, so editing or deleting that activity a year later cannot
 * change what an assessor signed.
 *
 * @property UnsurBkd $unsur
 */
class BkdBaris extends Model
{
    use HasFactory;

    protected $table = 'bkd_baris';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'unsur' => UnsurBkd::class,
            'sks_ratus' => 'integer',
            'otomatis' => 'boolean',
        ];
    }

    public function laporan(): BelongsTo
    {
        return $this->belongsTo(BkdLaporan::class, 'bkd_laporan_id');
    }

    public function penugasan(): BelongsTo
    {
        return $this->belongsTo(PenugasanDosen::class, 'penugasan_dosen_id');
    }

    /** Display only — arithmetic stays in hundredths. */
    public function sks(): float
    {
        return $this->sks_ratus / 100;
    }
}

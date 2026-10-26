<?php

declare(strict_types=1);

namespace App\Models\Pmb;

use App\Models\Akademik\Prodi;
use App\Traits\HasLogAktivitas;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One programme offered in one wave, with its seats, fee, threshold, and any
 * documents it asks for on top of the wave's.
 */
class PmbGelombangProdi extends Model
{
    use HasLogAktivitas;

    protected $table = 'pmb_gelombang_prodi';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'daya_tampung' => 'integer',
            'biaya_formulir' => 'integer',
            'ambang_lulus' => 'decimal:2',
            'dokumen_wajib' => 'array',
        ];
    }

    public function gelombang(): BelongsTo
    {
        return $this->belongsTo(PmbGelombang::class, 'pmb_gelombang_id');
    }

    public function prodi(): BelongsTo
    {
        return $this->belongsTo(Prodi::class);
    }
}

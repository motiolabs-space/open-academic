<?php

declare(strict_types=1);

namespace App\Models\Pmb;

use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One selection session in a wave: a test, an interview, a portfolio review.
 *
 * Only *when and where*. The test itself runs on whatever CBT the campus uses;
 * its scores come back through the import (see SeleksiPmbService).
 */
class PmbJadwalSeleksi extends Model
{
    use HasLogAktivitas;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'pmb_jadwal_seleksi';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'mulai' => 'datetime',
            'selesai' => 'datetime',
        ];
    }

    public function gelombang(): BelongsTo
    {
        return $this->belongsTo(PmbGelombang::class, 'pmb_gelombang_id');
    }
}

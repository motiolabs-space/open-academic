<?php

declare(strict_types=1);

namespace App\Models\Sdm;

use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use App\Traits\HasValidasiRiwayat;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A structural post — dean, head of programme, head of a bureau.
 *
 * Several may run at once and nothing reads a "current" one, so unlike rank
 * there is no pointer to keep honest. Whether the post lightens the BKD load is
 * the assessor's call (config/bkd.php), not a rule applied here.
 */
class JabatanStrukturalDosen extends Model
{
    use HasFactory;
    use HasLogAktivitas;
    use HasUuid;
    use HasValidasiRiwayat;
    use SoftDeletes;

    protected $table = 'jabatan_struktural_dosen';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'tanggal_sk' => 'date',
            'tmt' => 'date',
            'tmt_selesai' => 'date',
        ];
    }

    public function unitKerja(): BelongsTo
    {
        return $this->belongsTo(UnitKerja::class);
    }
}

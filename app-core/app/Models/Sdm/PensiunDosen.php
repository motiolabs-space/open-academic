<?php

declare(strict_types=1);

namespace App\Models\Sdm;

use App\Enums\JenisPensiun;
use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use App\Traits\HasValidasiRiwayat;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * The end of an employment.
 *
 * Approved, it closes the account once its TMT arrives — see
 * RiwayatKepegawaianService::tutupAkunPensiun().
 */
class PensiunDosen extends Model
{
    use HasFactory;
    use HasLogAktivitas;
    use HasUuid;
    use HasValidasiRiwayat;
    use SoftDeletes;

    protected $table = 'pensiun_dosen';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'jenis' => JenisPensiun::class,
            'tmt' => 'date',
            'tanggal_sk' => 'date',
        ];
    }
}

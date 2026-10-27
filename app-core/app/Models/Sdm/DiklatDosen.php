<?php

declare(strict_types=1);

namespace App\Models\Sdm;

use App\Enums\JenisDiklat;
use App\Enums\TingkatKegiatan;
use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use App\Traits\HasValidasiRiwayat;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** One training course a lecturer attended — SISTER's "Diklat" group. */
class DiklatDosen extends Model
{
    use HasFactory;
    use HasLogAktivitas;
    use HasUuid;
    use HasValidasiRiwayat;
    use SoftDeletes;

    protected $table = 'diklat_dosen';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'jenis' => JenisDiklat::class,
            'tingkat' => TingkatKegiatan::class,
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
            'jumlah_jam' => 'integer',
        ];
    }
}

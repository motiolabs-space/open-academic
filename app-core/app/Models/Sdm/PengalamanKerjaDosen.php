<?php

declare(strict_types=1);

namespace App\Models\Sdm;

use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use App\Traits\HasValidasiRiwayat;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** A post held outside this campus, before or alongside teaching. */
class PengalamanKerjaDosen extends Model
{
    use HasFactory;
    use HasLogAktivitas;
    use HasUuid;
    use HasValidasiRiwayat;
    use SoftDeletes;

    protected $table = 'pengalaman_kerja_dosen';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
        ];
    }
}

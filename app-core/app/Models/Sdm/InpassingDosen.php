<?php

declare(strict_types=1);

namespace App\Models\Sdm;

use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use App\Traits\HasValidasiRiwayat;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** The civil-service grade a non-civil-servant lecturer is held equivalent to. */
class InpassingDosen extends Model
{
    use HasFactory;
    use HasLogAktivitas;
    use HasUuid;
    use HasValidasiRiwayat;
    use SoftDeletes;

    protected $table = 'inpassing_dosen';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'tanggal_sk' => 'date',
            'tmt' => 'date',
            'angka_kredit_ratus' => 'integer',
        ];
    }
}

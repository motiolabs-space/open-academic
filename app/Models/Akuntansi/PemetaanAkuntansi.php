<?php

declare(strict_types=1);

namespace App\Models\Akuntansi;

use App\Enums\JenisEntitasAkuntansi;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A local record's identity on the accounting side.
 *
 * @property JenisEntitasAkuntansi $jenis
 */
class PemetaanAkuntansi extends Model
{
    use HasFactory;

    protected $table = 'akuntansi_pemetaan';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'jenis' => JenisEntitasAkuntansi::class,
            'dipetakan_at' => 'datetime',
        ];
    }

    /** The remote id for a local key, or null when it has never been pushed. */
    public static function cari(JenisEntitasAkuntansi $jenis, string $kunci): ?string
    {
        return static::query()
            ->where('jenis', $jenis->value)
            ->where('lokal_kunci', $kunci)
            ->value('easyerp_id');
    }
}

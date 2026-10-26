<?php

declare(strict_types=1);

namespace App\Models\Kemahasiswaan;

use App\Enums\JenisLokasiKegiatan;
use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A village or partner institution taking groups during one programme run.
 *
 * @property JenisLokasiKegiatan $jenis
 */
class KegiatanLapanganLokasi extends Model
{
    use HasLogAktivitas;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'kegiatan_lapangan_lokasi';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'jenis' => JenisLokasiKegiatan::class,
            'kuota_kelompok' => 'integer',
        ];
    }

    public function kegiatanLapangan(): BelongsTo
    {
        return $this->belongsTo(KegiatanLapangan::class);
    }

    public function kelompok(): HasMany
    {
        return $this->hasMany(KegiatanLapanganKelompok::class, 'lokasi_id');
    }

    /** "Desa Sukamaju, Kec. Cibodas, Kab. Bandung Barat" — what PDDIKTI receives as `lokasi`. */
    public function wilayah(): string
    {
        return collect([
            $this->nama,
            filled($this->kecamatan) ? 'Kec. '.$this->kecamatan : null,
            $this->kabupaten,
            $this->provinsi,
        ])->filter()->implode(', ');
    }
}

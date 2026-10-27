<?php

declare(strict_types=1);

namespace App\Models\Kemahasiswaan;

use App\Models\Sdm\Dosen;
use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A field-programme group: its members, where it is placed, and who supervises
 * it from the campus and from the partner.
 */
class KegiatanLapanganKelompok extends Model
{
    use HasLogAktivitas;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'kegiatan_lapangan_kelompok';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return ['nomor' => 'integer'];
    }

    public function kegiatanLapangan(): BelongsTo
    {
        return $this->belongsTo(KegiatanLapangan::class);
    }

    public function lokasi(): BelongsTo
    {
        return $this->belongsTo(KegiatanLapanganLokasi::class, 'lokasi_id');
    }

    public function pembimbing(): BelongsTo
    {
        return $this->belongsTo(Dosen::class, 'dosen_pembimbing_id');
    }

    public function anggota(): HasMany
    {
        return $this->hasMany(AktivitasMahasiswa::class, 'kegiatan_lapangan_kelompok_id');
    }
}

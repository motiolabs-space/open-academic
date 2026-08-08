<?php

declare(strict_types=1);

namespace App\Models\Akademik;

use App\Enums\JenisKonversi;
use App\Enums\StatusKonversi;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Staff;
use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One local course satisfied by learning done elsewhere.
 *
 * @property JenisKonversi $jenis
 * @property StatusKonversi $status
 */
class KonversiKredit extends Model
{
    use HasFactory;
    use HasLogAktivitas;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'konversi_kredit';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'jenis' => JenisKonversi::class,
            'status' => StatusKonversi::class,
            'bobot' => 'decimal:2',
            'diputus_at' => 'datetime',
        ];
    }

    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(Mahasiswa::class);
    }

    public function mataKuliah(): BelongsTo
    {
        return $this->belongsTo(MataKuliah::class);
    }

    public function pemutus(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'diputus_by_staff_id');
    }

    /** Granted conversions — the only ones that count anywhere. */
    public function scopeDiakui(Builder $query): Builder
    {
        return $query->where('status', StatusKonversi::Disetujui->value);
    }

    /**
     * The value written into kunci_aktif while a conversion is granted.
     *
     * Kept here rather than in the service so the two places that write it —
     * approval and reversal — cannot disagree about its shape.
     */
    public static function kunci(int $mahasiswaId, int $mataKuliahId): string
    {
        return $mahasiswaId.':'.$mataKuliahId;
    }
}

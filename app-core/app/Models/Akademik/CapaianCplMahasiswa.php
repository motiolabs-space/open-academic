<?php

declare(strict_types=1);

namespace App\Models\Akademik;

use App\Models\Kemahasiswaan\Mahasiswa;
use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A student's attainment of one CPL through one course, fixed when the class
 * was finalised. The cross-course figure is the credit-weighted mean of these
 * rows; see RekapCapaianService.
 */
class CapaianCplMahasiswa extends Model
{
    use HasLogAktivitas;
    use HasUuid;

    protected $table = 'capaian_cpl_mahasiswa';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'nilai' => 'float',
            'sks' => 'integer',
        ];
    }

    public function krsDetail(): BelongsTo
    {
        return $this->belongsTo(KrsDetail::class);
    }

    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(Mahasiswa::class);
    }

    public function kelasKuliah(): BelongsTo
    {
        return $this->belongsTo(KelasKuliah::class);
    }

    public function cpl(): BelongsTo
    {
        return $this->belongsTo(ProdiCpl::class, 'prodi_cpl_id');
    }
}

<?php

declare(strict_types=1);

namespace App\Models\Kemahasiswaan;

use App\Enums\CaraPenetapanWali;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\Staff;
use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One change of a student's academic advisor.
 *
 * An event, not a value: rows are written by DosenWaliService and never
 * edited. The current advisor is still read from `mahasiswa.dosen_wali_id`.
 */
class RiwayatDosenWali extends Model
{
    use HasLogAktivitas;
    use HasUuid;

    protected $table = 'riwayat_dosen_wali';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'cara' => CaraPenetapanWali::class,
            'krs_dialihkan' => 'integer',
        ];
    }

    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(Mahasiswa::class);
    }

    public function dosenLama(): BelongsTo
    {
        return $this->belongsTo(Dosen::class, 'dosen_lama_id');
    }

    public function dosenBaru(): BelongsTo
    {
        return $this->belongsTo(Dosen::class, 'dosen_baru_id');
    }

    public function penetap(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'ditetapkan_by_staff_id');
    }
}

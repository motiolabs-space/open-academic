<?php

declare(strict_types=1);

namespace App\Models\Kemahasiswaan;

use App\Enums\JenisLogbook;
use App\Models\Sdm\Dosen;
use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One entry in a participant's field log: a day, a week, or the final report.
 *
 * Hangs off the activity, not the student, so the log is evidence for that
 * programme run and nothing else.
 *
 * @property JenisLogbook $jenis
 */
class KegiatanLapanganLogbook extends Model
{
    use HasLogAktivitas;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'kegiatan_lapangan_logbook';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'jenis' => JenisLogbook::class,
            'tanggal' => 'date',
            'diperiksa_at' => 'datetime',
        ];
    }

    public function aktivitas(): BelongsTo
    {
        return $this->belongsTo(AktivitasMahasiswa::class, 'aktivitas_mahasiswa_id');
    }

    public function pemeriksa(): BelongsTo
    {
        return $this->belongsTo(Dosen::class, 'diperiksa_by_dosen_id');
    }

    public function sudahDiperiksa(): bool
    {
        return $this->diperiksa_at !== null;
    }
}

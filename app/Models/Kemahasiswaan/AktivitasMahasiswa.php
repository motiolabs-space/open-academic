<?php

declare(strict_types=1);

namespace App\Models\Kemahasiswaan;

use App\Enums\StudentActivityType;
use App\Models\Akademik\TahunAkademik;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\Staff;
use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An off-campus student activity (MBKM).
 *
 * The transactional source behind IKU 2. Open Campus reads verified rows over
 * Campus Bridge and decides which students clear the 20-credit threshold —
 * that scoring never happens here.
 */
class AktivitasMahasiswa extends Model
{
    use HasFactory;
    use HasLogAktivitas;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'aktivitas_mahasiswa';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'jenis' => StudentActivityType::class,
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
            'is_verified' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(Mahasiswa::class);
    }

    public function tahunAkademik(): BelongsTo
    {
        return $this->belongsTo(TahunAkademik::class);
    }

    public function pembimbing(): BelongsTo
    {
        return $this->belongsTo(Dosen::class, 'dosen_pembimbing_id');
    }

    public function verifikator(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'verified_by_staff_id');
    }

    /** @param Builder<self> $query */
    public function scopeTerverifikasi(Builder $query): Builder
    {
        return $query->where('is_verified', true);
    }
}

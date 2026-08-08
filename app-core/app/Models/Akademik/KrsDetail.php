<?php

declare(strict_types=1);

namespace App\Models\Akademik;

use App\Models\Kemahasiswaan\AktivitasMahasiswa;
use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One class taken within a study plan. The grade hangs off this row, which is
 * why the class reference is restrict-on-delete: removing an offering must
 * never silently orphan a grade.
 */
class KrsDetail extends Model
{
    use HasFactory;
    use HasLogAktivitas;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'krs_detail';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'is_mengulang' => 'boolean',
            'feeder_synced_at' => 'datetime',
        ];
    }

    public function krs(): BelongsTo
    {
        return $this->belongsTo(Krs::class);
    }

    public function kelasKuliah(): BelongsTo
    {
        return $this->belongsTo(KelasKuliah::class);
    }

    public function nilai(): HasOne
    {
        return $this->hasOne(Nilai::class);
    }

    public function nilaiKomponen(): HasMany
    {
        return $this->hasMany(NilaiKomponen::class);
    }

    /** Set when the credits are recognised from an MBKM activity. */
    public function aktivitas(): BelongsTo
    {
        return $this->belongsTo(AktivitasMahasiswa::class, 'aktivitas_mahasiswa_id');
    }

    public function konversiMbkm(): bool
    {
        return $this->aktivitas_mahasiswa_id !== null;
    }
}

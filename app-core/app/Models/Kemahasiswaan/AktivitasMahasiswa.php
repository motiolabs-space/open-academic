<?php

declare(strict_types=1);

namespace App\Models\Kemahasiswaan;

use App\Enums\StudentActivityType;
use App\Models\Akademik\KrsDetail;
use App\Models\Akademik\TahunAkademik;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\Staff;
use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An off-campus student activity (MBKM, KKN, PKL).
 *
 * The transactional source behind IKU 2. Open Campus reads verified rows over
 * Campus Bridge and decides which students clear the 20-credit threshold —
 * that scoring never happens here.
 *
 * A participant of a managed field programme (#37) is a row like any other,
 * linked to its programme and group; see KegiatanLapanganService.
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
            'feeder_synced_at' => 'datetime',
            'jenis' => StudentActivityType::class,
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
            'is_verified' => 'boolean',
            'verified_at' => 'datetime',
            'is_ketua_kelompok' => 'boolean',
            'nilai_pembimbing' => 'decimal:2',
            'nilai_mitra' => 'decimal:2',
            'nilai_akhir' => 'decimal:2',
            'dinilai_at' => 'datetime',
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

    public function kegiatanLapangan(): BelongsTo
    {
        return $this->belongsTo(KegiatanLapangan::class);
    }

    public function kelompok(): BelongsTo
    {
        return $this->belongsTo(KegiatanLapanganKelompok::class, 'kegiatan_lapangan_kelompok_id');
    }

    public function logbook(): HasMany
    {
        return $this->hasMany(KegiatanLapanganLogbook::class)->orderByDesc('tanggal')->orderByDesc('id');
    }

    /** The KRS line through which the activity's credits were recognised, if any. */
    public function krsKonversi(): HasOne
    {
        return $this->hasOne(KrsDetail::class, 'aktivitas_mahasiswa_id');
    }

    /** @param Builder<self> $query */
    public function scopeTerverifikasi(Builder $query): Builder
    {
        return $query->where('is_verified', true);
    }
}

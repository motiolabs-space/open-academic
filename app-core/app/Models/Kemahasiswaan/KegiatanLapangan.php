<?php

declare(strict_types=1);

namespace App\Models\Kemahasiswaan;

use App\Enums\StudentActivityType;
use App\Models\Akademik\TahunAkademik;
use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One run of a field programme — a KKN period, a PKL batch.
 *
 * It owns locations and groups, but not participants: each participant is an
 * `aktivitas_mahasiswa` row pointing back here, so what PDDIKTI receives is the
 * same activity chain an MBKM internship travels (ADR-029).
 *
 * @property StudentActivityType $jenis
 */
class KegiatanLapangan extends Model
{
    use HasLogAktivitas;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'kegiatan_lapangan';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'jenis' => StudentActivityType::class,
            'tanggal_sk' => 'date',
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
            'pendaftaran_dibuka' => 'boolean',
            'campur_prodi' => 'boolean',
            'campur_jenis_kelamin' => 'boolean',
            'min_sks' => 'integer',
            'ukuran_kelompok' => 'integer',
            'bobot_mitra' => 'integer',
        ];
    }

    public function tahunAkademik(): BelongsTo
    {
        return $this->belongsTo(TahunAkademik::class);
    }

    public function lokasi(): HasMany
    {
        return $this->hasMany(KegiatanLapanganLokasi::class)->orderBy('nama');
    }

    public function kelompok(): HasMany
    {
        return $this->hasMany(KegiatanLapanganKelompok::class)->orderBy('nomor');
    }

    public function peserta(): HasMany
    {
        return $this->hasMany(AktivitasMahasiswa::class);
    }
}

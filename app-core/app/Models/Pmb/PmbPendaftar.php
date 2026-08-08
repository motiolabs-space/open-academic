<?php

declare(strict_types=1);

namespace App\Models\Pmb;

use App\Enums\ApplicantStatus;
use App\Enums\Gender;
use App\Models\Akademik\Prodi;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Traits\DapatDicari;
use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An admission applicant.
 *
 * NIK and place/date of birth are collected here rather than after enrolment,
 * because a Feeder biodata push fails without them — catching the gap at
 * registration is far cheaper than chasing a student down a year later.
 */
class PmbPendaftar extends Model
{
    use DapatDicari;
    use HasFactory;
    use HasLogAktivitas;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'pmb_pendaftar';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'status' => ApplicantStatus::class,
            'jenis_kelamin' => Gender::class,
            'tanggal_lahir' => 'date',
            'nilai_seleksi' => 'decimal:2',
        ];
    }

    public function gelombang(): BelongsTo
    {
        return $this->belongsTo(PmbGelombang::class, 'pmb_gelombang_id');
    }

    public function prodiPilihan1(): BelongsTo
    {
        return $this->belongsTo(Prodi::class, 'prodi_pilihan_1_id');
    }

    public function prodiPilihan2(): BelongsTo
    {
        return $this->belongsTo(Prodi::class, 'prodi_pilihan_2_id');
    }

    public function prodiDiterima(): BelongsTo
    {
        return $this->belongsTo(Prodi::class, 'prodi_diterima_id');
    }

    /** Set once the applicant has been converted into a student record. */
    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(Mahasiswa::class);
    }

    public function berkas(): HasMany
    {
        return $this->hasMany(PmbBerkas::class);
    }

    /** Data completeness that a future Feeder biodata push depends on. */
    public function siapSinkronFeeder(): bool
    {
        return filled($this->nik)
            && filled($this->tempat_lahir)
            && $this->tanggal_lahir !== null
            && $this->jenis_kelamin !== null;
    }

    /** @param Builder<self> $query */
    public function scopeStatus(Builder $query, ApplicantStatus $status): Builder
    {
        return $query->where('status', $status->value);
    }
}

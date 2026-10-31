<?php

declare(strict_types=1);

namespace App\Models\Kemahasiswaan;

use App\Enums\JenisDaftar;
use App\Enums\JenisRegistrasiUlang;
use App\Enums\StudentStatus;
use App\Models\Akademik\Prodi;
use App\Models\Akademik\TahunAkademik;
use App\Models\Sdm\Staff;
use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One re-NIM, readmission, or reactivation of a student's registration.
 *
 * An event, not a correction: written once by RegistrasiUlangService, never
 * edited. A mistaken one is soft-deleted with its reason in the activity log
 * and recorded again.
 */
class RiwayatRegistrasiMahasiswa extends Model
{
    use HasLogAktivitas;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'riwayat_registrasi_mahasiswa';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'jenis' => JenisRegistrasiUlang::class,
            'jenis_daftar' => JenisDaftar::class,
            'status_sebelum' => StudentStatus::class,
            'status_sesudah' => StudentStatus::class,
            'tanggal' => 'date',
            'tanggal_sk' => 'date',
        ];
    }

    public function mahasiswaLama(): BelongsTo
    {
        return $this->belongsTo(Mahasiswa::class, 'mahasiswa_lama_id');
    }

    public function mahasiswaBaru(): BelongsTo
    {
        return $this->belongsTo(Mahasiswa::class, 'mahasiswa_baru_id');
    }

    public function prodiLama(): BelongsTo
    {
        return $this->belongsTo(Prodi::class, 'prodi_lama_id');
    }

    public function prodiBaru(): BelongsTo
    {
        return $this->belongsTo(Prodi::class, 'prodi_baru_id');
    }

    public function tahunAkademik(): BelongsTo
    {
        return $this->belongsTo(TahunAkademik::class);
    }

    public function pencatat(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'dicatat_by_staff_id');
    }
}

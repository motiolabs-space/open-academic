<?php

declare(strict_types=1);

namespace App\Models\Sdm;

use App\Enums\EducationLevel;
use App\Enums\JenisStudiLanjut;
use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use App\Traits\HasValidasiRiwayat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Study leave, or permission to study alongside the job.
 *
 * Approved study leave suspends the BKD obligation for the semesters it
 * overlaps — read by BkdService::wajibMelapor(), not copied anywhere.
 */
class TugasBelajarDosen extends Model
{
    use HasFactory;
    use HasLogAktivitas;
    use HasUuid;
    use HasValidasiRiwayat;
    use SoftDeletes;

    protected $table = 'tugas_belajar_dosen';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'jenis' => JenisStudiLanjut::class,
            'jenjang' => EducationLevel::class,
            'tanggal_sk' => 'date',
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
        ];
    }

    /**
     * Approved study leave that overlaps a period, open-ended leave included.
     *
     * Izin belajar never matches: studying alongside the job leaves every
     * obligation in place.
     *
     * @param Builder<self> $query
     */
    public function scopeMembebaskanBkdPada(Builder $query, string $mulai, string $selesai): Builder
    {
        return $query->disetujui()
            ->where('jenis', JenisStudiLanjut::TugasBelajar->value)
            ->where('tanggal_mulai', '<=', $selesai)
            ->where(fn (Builder $q) => $q->whereNull('tanggal_selesai')->orWhere('tanggal_selesai', '>=', $mulai));
    }
}

<?php

declare(strict_types=1);

namespace App\Models\Sdm;

use App\Enums\JenisLuaran;
use App\Enums\LecturerAssignmentType;
use App\Enums\PeranKegiatan;
use App\Enums\TingkatKegiatan;
use App\Enums\UnsurBkd;
use App\Models\Akademik\TahunAkademik;
use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Everything a lecturer did that was not standing in front of a class.
 *
 * One record with three readers: BKD sorts it into an element and weighs it,
 * the SISTER portfolio reports its output, and IKU 3/4 count it as evidence.
 * The alternative — a second table for BKD activities — means research recorded
 * twice and two copies that disagree within a semester.
 *
 * @property LecturerAssignmentType $jenis
 * @property ?UnsurBkd $unsur
 * @property ?PeranKegiatan $peran
 * @property ?TingkatKegiatan $tingkat
 * @property ?JenisLuaran $luaran_jenis
 */
class PenugasanDosen extends Model
{
    use HasFactory;
    use HasLogAktivitas;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'penugasan_dosen';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'jenis' => LecturerAssignmentType::class,
            'unsur' => UnsurBkd::class,
            'peran' => PeranKegiatan::class,
            'tingkat' => TingkatKegiatan::class,
            'luaran_jenis' => JenisLuaran::class,
            'luaran_tahun' => 'integer',
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
            'sks_ekuivalen' => 'decimal:2',
            'is_verified' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    public function dosen(): BelongsTo
    {
        return $this->belongsTo(Dosen::class);
    }

    public function tahunAkademik(): BelongsTo
    {
        return $this->belongsTo(TahunAkademik::class);
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

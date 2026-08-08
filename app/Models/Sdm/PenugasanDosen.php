<?php

declare(strict_types=1);

namespace App\Models\Sdm;

use App\Enums\LecturerAssignmentType;
use App\Models\Akademik\TahunAkademik;
use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An external or non-teaching assignment held by a lecturer.
 * Verified rows are the evidence Open Campus reads for IKU 3 and IKU 4.
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

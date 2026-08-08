<?php

declare(strict_types=1);

namespace App\Models\Akademik;

use App\Enums\GradeLetter;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Dosen;
use App\Traits\DapatDicari;
use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * The final grade for one taken class.
 *
 * A grade is an event. Once `is_final` is set it is locked: corrections go
 * through the audited correction path (which records catatan_koreksi and a new
 * log entry), never through a silent update.
 */
class Nilai extends Model
{
    use DapatDicari;
    use HasFactory;
    use HasLogAktivitas;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'nilai';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'nilai_angka' => 'decimal:2',
            'nilai_huruf' => GradeLetter::class,
            'bobot' => 'decimal:2',
            'is_final' => 'boolean',
            'finalized_at' => 'datetime',
            'feeder_synced_at' => 'datetime',
        ];
    }

    public function krsDetail(): BelongsTo
    {
        return $this->belongsTo(KrsDetail::class);
    }

    public function kelasKuliah(): BelongsTo
    {
        return $this->belongsTo(KelasKuliah::class);
    }

    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(Mahasiswa::class);
    }

    public function pemfinalisasi(): BelongsTo
    {
        return $this->belongsTo(Dosen::class, 'finalized_by_dosen_id');
    }

    /** Grade points earned: grade weight × course credits. */
    public function mutu(): float
    {
        return (float) $this->bobot * $this->krsDetail->sks;
    }

    public function lulus(): bool
    {
        return $this->nilai_huruf?->isPassing() ?? false;
    }

    /** @param Builder<self> $query */
    public function scopeFinal(Builder $query): Builder
    {
        return $query->where('is_final', true);
    }
}

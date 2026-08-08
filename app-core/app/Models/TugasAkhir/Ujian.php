<?php

declare(strict_types=1);

namespace App\Models\TugasAkhir;

use App\Enums\HasilUjian;
use App\Enums\JenisUjian;
use App\Enums\StatusUjian;
use App\Models\Akademik\Ruang;
use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A scheduled examination — proposal seminar, results seminar, or defence.
 *
 * @property JenisUjian $jenis
 * @property StatusUjian $status
 * @property ?HasilUjian $hasil
 * @property Collection<int, Penguji> $penguji
 */
class Ujian extends Model
{
    use HasFactory;
    use HasLogAktivitas;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'tugas_akhir_ujian';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'jenis' => JenisUjian::class,
            'status' => StatusUjian::class,
            'hasil' => HasilUjian::class,
            'tanggal' => 'date',
            'batas_revisi' => 'date',
            'nilai' => 'decimal:2',
        ];
    }

    public function tugasAkhir(): BelongsTo
    {
        return $this->belongsTo(TugasAkhir::class);
    }

    public function ruang(): BelongsTo
    {
        return $this->belongsTo(Ruang::class);
    }

    public function penguji(): HasMany
    {
        return $this->hasMany(Penguji::class, 'tugas_akhir_ujian_id')->orderBy('peran');
    }

    /**
     * Mean of the marks the panel actually entered.
     *
     * Members who have not marked yet are excluded rather than counted as zero:
     * a partial panel average that looks like a fail is worse than no average.
     * Returns null until at least one mark exists.
     */
    public function rerataPenguji(): ?float
    {
        $nilai = $this->penguji
            ->filter(fn (Penguji $p): bool => $p->nilai !== null)
            ->map(fn (Penguji $p): float => (float) $p->nilai);

        return $nilai->isEmpty() ? null : round((float) $nilai->avg(), 2);
    }

    public function semuaPengujiMenilai(): bool
    {
        return $this->penguji->isNotEmpty()
            && $this->penguji->every(fn (Penguji $p): bool => $p->nilai !== null);
    }

    public function selesai(): bool
    {
        return $this->status === StatusUjian::Selesai;
    }
}

<?php

declare(strict_types=1);

namespace App\Models\Sdm;

use App\Enums\KesimpulanBkd;
use App\Enums\StatusBkd;
use App\Enums\UnsurBkd;
use App\Models\Akademik\TahunAkademik;
use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One lecturer's workload report for one semester.
 *
 * @property StatusBkd $status
 * @property ?KesimpulanBkd $kesimpulan
 */
class BkdLaporan extends Model
{
    use HasFactory;
    use HasLogAktivitas;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'bkd_laporan';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'status' => StatusBkd::class,
            'kesimpulan' => KesimpulanBkd::class,
            'diajukan_at' => 'datetime',
            'dinilai_at' => 'datetime',
            'disahkan_at' => 'datetime',
            'sks_pendidikan' => 'integer',
            'sks_penelitian' => 'integer',
            'sks_pengabdian' => 'integer',
            'sks_penunjang' => 'integer',
            'sks_total' => 'integer',
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

    public function baris(): HasMany
    {
        return $this->hasMany(BkdBaris::class, 'bkd_laporan_id')->orderBy('unsur')->orderBy('urutan');
    }

    public function asesor1(): BelongsTo
    {
        return $this->belongsTo(Dosen::class, 'asesor_1_dosen_id');
    }

    public function asesor2(): BelongsTo
    {
        return $this->belongsTo(Dosen::class, 'asesor_2_dosen_id');
    }

    public function pengesah(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'disahkan_by_staff_id');
    }

    /** Stored credit for one element, as a decimal — display only. */
    public function sks(UnsurBkd $unsur): float
    {
        return $this->{'sks_'.$unsur->value} / 100;
    }

    public function sksTotal(): float
    {
        return $this->sks_total / 100;
    }

    /**
     * Whether a named lecturer is one of this report's assessors.
     *
     * The only question the assessor screen asks, and it lives here so that
     * screen and policy cannot answer it differently.
     */
    public function dinilaiOleh(Dosen $dosen): bool
    {
        return in_array($dosen->id, [$this->asesor_1_dosen_id, $this->asesor_2_dosen_id], true);
    }

    /** @param Builder<self> $query */
    public function scopeMenungguPenilaian(Builder $query): Builder
    {
        return $query->where('status', StatusBkd::Diajukan->value);
    }
}

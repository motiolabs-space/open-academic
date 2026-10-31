<?php

declare(strict_types=1);

namespace App\Models\Keuangan;

use App\Enums\StatusAjuanBeasiswa;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Staff;
use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One student's application to a call for scholarship applications.
 *
 * @property StatusAjuanBeasiswa $status
 * @property array<int, array{label: string, path: string}>|null $berkas
 */
class BeasiswaAjuan extends Model
{
    use HasLogAktivitas;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'beasiswa_ajuan';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'status' => StatusAjuanBeasiswa::class,
            'ipk_saat_ajuan' => 'decimal:2',
            'berkas' => 'array',
            'diajukan_at' => 'datetime',
            'diputus_at' => 'datetime',
        ];
    }

    public function periode(): BelongsTo
    {
        return $this->belongsTo(BeasiswaPeriode::class, 'beasiswa_periode_id');
    }

    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(Mahasiswa::class);
    }

    public function pemutus(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'diputus_by_staff_id');
    }

    /** The award a granted application produced. */
    public function penerima(): BelongsTo
    {
        return $this->belongsTo(BeasiswaPenerima::class, 'beasiswa_penerima_id');
    }

    /** @param Builder<self> $query */
    public function scopeMenunggu(Builder $query): Builder
    {
        return $query->where('status', StatusAjuanBeasiswa::Diajukan->value);
    }
}

<?php

declare(strict_types=1);

namespace App\Models\Keuangan;

use App\Models\Akademik\TahunAkademik;
use App\Models\Sdm\Staff;
use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A call for applications under one scholarship scheme.
 *
 * @property array<int, string>|null $berkas_diminta
 */
class BeasiswaPeriode extends Model
{
    use HasLogAktivitas;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'beasiswa_periode';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'dibuka_mulai' => 'date',
            'dibuka_sampai' => 'date',
            'ipk_minimum' => 'decimal:2',
            'berkas_diminta' => 'array',
            'ditutup_at' => 'datetime',
        ];
    }

    public function beasiswa(): BelongsTo
    {
        return $this->belongsTo(Beasiswa::class);
    }

    public function tahunAkademik(): BelongsTo
    {
        return $this->belongsTo(TahunAkademik::class);
    }

    public function ajuan(): HasMany
    {
        return $this->hasMany(BeasiswaAjuan::class);
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'dibuat_by_staff_id');
    }

    /** Open today: inside its dates and not closed early. */
    public function dibuka(): bool
    {
        $hariIni = Carbon::today();

        return $this->ditutup_at === null
            && $hariIni->gte($this->dibuka_mulai)
            && $hariIni->lte($this->dibuka_sampai);
    }

    /** @param Builder<self> $query */
    public function scopeTerbuka(Builder $query): Builder
    {
        $hariIni = Carbon::today()->toDateString();

        return $query->whereNull('ditutup_at')
            ->whereDate('dibuka_mulai', '<=', $hariIni)
            ->whereDate('dibuka_sampai', '>=', $hariIni);
    }

    /** @return array<int, string> */
    public function daftarBerkas(): array
    {
        return array_values($this->berkas_diminta ?? []);
    }
}

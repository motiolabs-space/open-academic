<?php

declare(strict_types=1);

namespace App\Models\Kinerja;

use App\Enums\SumberRealisasi;
use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A measure: one target, and one way of learning what actually happened.
 *
 * `sumber_realisasi` is the column that decides whether this module is useful
 * or merely a form. A measure that is computed cannot be polished before a
 * review, because its number never arrives from a form.
 */
class UkuranKinerja extends Model
{
    use HasFactory;
    use HasLogAktivitas;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'ukuran_kinerja';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected $attributes = [
        'sumber_realisasi' => SumberRealisasi::Dilaporkan->value,
        'semakin_besar_semakin_baik' => true,
    ];

    protected function casts(): array
    {
        return [
            'sumber_realisasi' => SumberRealisasi::class,
            'target' => 'decimal:2',
            'target_beku' => 'decimal:2',
            'realisasi_beku' => 'decimal:2',
            'semakin_besar_semakin_baik' => 'boolean',
        ];
    }

    public function sasaran(): BelongsTo
    {
        return $this->belongsTo(SasaranKinerja::class, 'sasaran_kinerja_id');
    }

    public function capaian(): HasMany
    {
        return $this->hasMany(CapaianKinerja::class, 'ukuran_kinerja_id');
    }

    /**
     * The realisation in force.
     *
     * Once frozen, the frozen copy wins outright — a locked period must read
     * the same next year as it did on the day it was reported, whatever the
     * underlying data does afterwards.
     */
    public function realisasi(): ?float
    {
        if ($this->realisasi_beku !== null) {
            return (float) $this->realisasi_beku;
        }

        $terakhir = $this->relationLoaded('capaian')
            ? $this->capaian->sortByDesc('tanggal')->first()
            : $this->capaian()->orderByDesc('tanggal')->first();

        return $terakhir === null ? null : (float) $terakhir->nilai;
    }

    public function targetBerlaku(): float
    {
        return (float) ($this->target_beku ?? $this->target);
    }

    public function beku(): bool
    {
        return $this->target_beku !== null;
    }

    /**
     * Progress against target, as a percentage.
     *
     * Inverted for measures where smaller is better — a drop-out figure at half
     * its ceiling is 200% of the way there, not 50%. Null when nothing has been
     * measured yet, because zero and "not yet known" are different states and a
     * screen that renders them alike invites the wrong conversation.
     */
    public function persenCapaian(): ?float
    {
        $realisasi = $this->realisasi();
        $target = $this->targetBerlaku();

        if ($realisasi === null || $target == 0.0) {
            return null;
        }

        $persen = $this->semakin_besar_semakin_baik
            ? $realisasi / $target * 100
            : $target / max($realisasi, 0.01) * 100;

        return round($persen, 1);
    }

    /**
     * What the percentage crosses — a label, never a verdict about a person.
     *
     * @return array{sebutan: string, tone: string}|null
     */
    public function statusCapaian(): ?array
    {
        $persen = $this->persenCapaian();

        if ($persen === null) {
            return null;
        }

        foreach ((array) config('kinerja.ambang_capaian', []) as $ambang) {
            if ($persen >= (float) $ambang['persen']) {
                return ['sebutan' => $ambang['sebutan'], 'tone' => $ambang['tone']];
            }
        }

        return null;
    }
}

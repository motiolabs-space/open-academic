<?php

declare(strict_types=1);

namespace App\Models\Keuangan;

use App\Enums\JenisItemTagihan;
use App\Enums\KomponenTarif;
use App\Models\Akademik\KrsDetail;
use App\Models\Akademik\MataKuliah;
use App\Models\Sdm\Staff;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of an invoice.
 *
 * Charges are snapshotted from the fee matrix at generation; reductions are
 * added later, carry a negative amount, and record who granted them and why.
 *
 * Keeping both on one table is what preserves the invariant the rest of the
 * application leans on: `tagihan.total` is the sum of its lines, and therefore
 * always what the student actually owes.
 *
 * Lines billed from the approved KRS carry the course and plan row they came
 * from. They are never edited: a dropped class is answered by a second line
 * naming the first in `koreksi_atas_item_id` — see TagihanKrsService.
 *
 * @property JenisItemTagihan $jenis
 * @property KomponenTarif|null $komponen
 */
class TagihanItem extends Model
{
    use HasFactory;

    protected $table = 'tagihan_item';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'nominal' => 'integer',
            'jenis' => JenisItemTagihan::class,
            'komponen' => KomponenTarif::class,
            'diputus_at' => 'datetime',
        ];
    }

    public function tagihan(): BelongsTo
    {
        return $this->belongsTo(Tagihan::class);
    }

    public function tarif(): BelongsTo
    {
        return $this->belongsTo(Tarif::class);
    }

    public function mataKuliah(): BelongsTo
    {
        return $this->belongsTo(MataKuliah::class);
    }

    /** The plan row, even after the class was dropped from the plan. */
    public function krsDetail(): BelongsTo
    {
        return $this->belongsTo(KrsDetail::class)->withTrashed();
    }

    /** The earlier line this one corrects. */
    public function dikoreksi(): BelongsTo
    {
        return $this->belongsTo(self::class, 'koreksi_atas_item_id');
    }

    public function penerima(): BelongsTo
    {
        return $this->belongsTo(BeasiswaPenerima::class, 'beasiswa_penerima_id');
    }

    public function pemutus(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'diputus_by_staff_id');
    }

    public function scopeTagihan(Builder $query): Builder
    {
        return $query->where('jenis', JenisItemTagihan::Tagihan->value);
    }

    public function scopePotongan(Builder $query): Builder
    {
        return $query->where('jenis', JenisItemTagihan::Potongan->value);
    }

    public function scopeDenda(Builder $query): Builder
    {
        return $query->where('jenis', JenisItemTagihan::Denda->value);
    }

    /** Lines billed from the study plan, corrections included. */
    public function scopeDariKrs(Builder $query): Builder
    {
        return $query->whereNotNull('mata_kuliah_id')->where('jenis', JenisItemTagihan::Tagihan->value);
    }

    /** The absolute value, for a screen that already shows the minus sign. */
    public function besaran(): int
    {
        return abs((int) $this->nominal);
    }
}

<?php

declare(strict_types=1);

namespace App\Models\Keuangan;

use App\Enums\InvoiceStatus;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Traits\DapatDicari;
use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A term invoice.
 *
 * Money is stored in whole rupiah as integers — no float ever touches an
 * amount. The KRS lock reads `terbayar` against the configured minimum
 * percentage, which is why partial payment is a first-class state rather than
 * an afterthought.
 */
class Tagihan extends Model
{
    use DapatDicari;
    use HasFactory;
    use HasLogAktivitas;
    use HasUuid;
    use SoftDeletes;

    protected $table = 'tagihan';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'total' => 'integer',
            'terbayar' => 'integer',
            'jatuh_tempo' => 'date',
            'dispensasi_sampai' => 'date',
        ];
    }

    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(Mahasiswa::class);
    }

    public function tahunAkademik(): BelongsTo
    {
        return $this->belongsTo(TahunAkademik::class);
    }

    public function item(): HasMany
    {
        return $this->hasMany(TagihanItem::class);
    }

    public function pembayaran(): HasMany
    {
        return $this->hasMany(Pembayaran::class)->latest();
    }

    public function sisa(): int
    {
        return max(0, $this->total - $this->terbayar);
    }

    public function persenTerbayar(): float
    {
        return $this->total > 0 ? round($this->terbayar / $this->total * 100, 2) : 100.0;
    }

    /**
     * Money paid beyond what is now owed.
     *
     * Arises when a reduction lands after payment: a student settles in full in
     * August and their scholarship is confirmed in September. The payment rows
     * are not rewritten — the money did change hands — so the excess sits here
     * for the finance office to refund or carry forward.
     *
     * Surfaced rather than absorbed. An overpayment that quietly disappears into
     * a recalculated total is money the campus took and cannot account for.
     */
    public function kelebihanBayar(): int
    {
        return max(0, (int) $this->terbayar - (int) $this->total);
    }

    /** Charges before any reduction, for a screen that shows both. */
    public function totalKotor(): int
    {
        return (int) $this->item()->tagihan()->sum('nominal');
    }

    public function totalPotongan(): int
    {
        return abs((int) $this->item()->potongan()->sum('nominal'));
    }

    public function terlambat(): bool
    {
        return $this->status !== InvoiceStatus::Lunas
            && $this->jatuh_tempo->isPast()
            && !$this->dispensasiAktif();
    }

    public function dispensasiAktif(): bool
    {
        return $this->dispensasi_sampai !== null
            && $this->dispensasi_sampai->gte(Carbon::today());
    }

    /**
     * Whether this invoice currently satisfies the KRS payment gate.
     * A live dispensation lifts the gate regardless of the balance.
     */
    public function memenuhiSyaratKrs(): bool
    {
        if (!config('academic.krs.requires_payment')) {
            return true;
        }

        if ($this->dispensasiAktif()) {
            return true;
        }

        return $this->persenTerbayar() >= (float) config('academic.krs.min_payment_percent');
    }

    /** @param Builder<self> $query */
    public function scopeBelumLunas(Builder $query): Builder
    {
        return $query->whereIn('status', [
            InvoiceStatus::BelumBayar->value,
            InvoiceStatus::Sebagian->value,
        ]);
    }
}

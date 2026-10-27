<?php

declare(strict_types=1);

namespace App\Models\Keuangan;

use App\Enums\InvoiceStatus;
use App\Enums\JenisItemTagihan;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Services\Keuangan\DendaService;
use App\Traits\DapatDicari;
use App\Traits\HasLogAktivitas;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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

    /**
     * The accrual watermark moves every night on every overdue invoice. An
     * audit row for each move would bury the entries that matter — the fee
     * being charged is logged explicitly by DendaService instead.
     *
     * @var array<int, string>
     */
    protected array $logExcept = ['denda_dihitung_sampai'];

    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'total' => 'integer',
            'nominal_krs' => 'integer',
            'terbayar' => 'integer',
            'denda' => 'integer',
            'jatuh_tempo' => 'date',
            'dispensasi_sampai' => 'date',
            'denda_dihitung_sampai' => 'date',
            'denda_dibebaskan_at' => 'datetime',
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

    /** The one late-fee line, once accrual has charged anything. */
    public function itemDenda(): HasOne
    {
        return $this->hasOne(TagihanItem::class)->where('jenis', JenisItemTagihan::Denda->value);
    }

    /** The waiver line, once the finance office has forgiven the fee. */
    public function pembebasanDenda(): HasOne
    {
        return $this->hasOne(TagihanItem::class)
            ->where('jenis', JenisItemTagihan::Potongan->value)
            ->where('nama', DendaService::NAMA_PEMBEBASAN);
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
     * What the student owes before any late fee: charges minus reductions.
     *
     * `denda` is the fee currently inside `total`, so subtracting it here needs
     * no query — which matters because the KRS gate asks this for a whole
     * cohort at registration time.
     */
    public function pokok(): int
    {
        return max(0, (int) $this->total - (int) $this->denda);
    }

    /** Principal still outstanding; zero once payments cover the principal. */
    public function sisaPokok(): int
    {
        return max(0, $this->pokok() - (int) $this->terbayar);
    }

    /**
     * Payment progress measured against the principal.
     *
     * The gate reads this, not `persenTerbayar()`. A student who paid exactly
     * the threshold and then incurred a fee has not become less compliant with
     * the payment rule; the rule is about tuition, and the fee is a separate
     * question for the finance office.
     */
    public function persenTerbayarPokok(): float
    {
        $pokok = $this->pokok();

        return $pokok > 0 ? round(min((int) $this->terbayar, $pokok) / $pokok * 100, 2) : 100.0;
    }

    /**
     * The part of the principal the KRS gate measures: what is owed for being
     * enrolled, not for what the plan contains.
     *
     * Per-credit and per-course charges are billed when the plan is approved,
     * so counting them here would be circular — a student could not submit the
     * plan until they had paid for it, and could not be billed for it until it
     * was submitted. That bites in the revision window, when a plan already
     * billed is reopened and has to pass the gate again. See ADR-030.
     *
     * Reductions are shared pro rata between the fixed and the KRS-derived
     * charges, because a scholarship line does not say which charge it paid
     * for. Zero `nominal_krs` — every campus that does not bill by credit —
     * returns the principal without a query.
     */
    public function pokokGerbangKrs(): int
    {
        $pokok = $this->pokok();
        $dariKrs = (int) $this->nominal_krs;

        if ($dariKrs <= 0) {
            return $pokok;
        }

        $kotor = $this->totalKotor();
        $tetap = max(0, $kotor - $dariKrs);

        if ($kotor <= 0 || $tetap === 0) {
            return 0;
        }

        return intdiv($pokok * $tetap, $kotor);
    }

    /** Payment progress against pokokGerbangKrs(); what the KRS gate reads. */
    public function persenTerbayarGerbangKrs(): float
    {
        $dasar = $this->pokokGerbangKrs();

        return $dasar > 0 ? round(min((int) $this->terbayar, $dasar) / $dasar * 100, 2) : 100.0;
    }

    public function dendaDibebaskan(): bool
    {
        return $this->denda_dibebaskan_at !== null;
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
     *
     * A live dispensation lifts the gate regardless of the balance. A late fee
     * never tightens it: the threshold is measured against the principal, so
     * the gate answers exactly as it did before any fee was charged. Nor does a
     * charge billed from the approved plan — see pokokGerbangKrs().
     */
    public function memenuhiSyaratKrs(): bool
    {
        if (!config('academic.krs.requires_payment')) {
            return true;
        }

        if ($this->dispensasiAktif()) {
            return true;
        }

        return $this->persenTerbayarGerbangKrs() >= (float) config('academic.krs.min_payment_percent');
    }

    /** @param Builder<self> $query */
    public function scopeBelumLunas(Builder $query): Builder
    {
        return $query->whereIn('status', [
            InvoiceStatus::BelumBayar->value,
            InvoiceStatus::Sebagian->value,
        ]);
    }

    /**
     * Outstanding balance per student, in one grouped query.
     *
     * The single definition of "bebas tanggungan keuangan", read by both the
     * graduation checklist and the ceremony checklist so the two can never
     * disagree about the same invoice. `total` is the net figure: an unpaid
     * late fee is in it and counts as arrears; a waived one was cancelled by
     * its own negative row and does not.
     *
     * @param array<int, int> $mahasiswaIds
     * @return Collection<int, int> keyed by student id; students who owe nothing are absent
     */
    public static function tunggakanPerMahasiswa(array $mahasiswaIds): Collection
    {
        if ($mahasiswaIds === []) {
            return collect();
        }

        return static::query()
            ->whereIn('mahasiswa_id', $mahasiswaIds)
            ->belumLunas()
            ->groupBy('mahasiswa_id')
            ->pluck(DB::raw('SUM(total - terbayar)'), 'mahasiswa_id')
            ->map(fn ($n): int => (int) $n);
    }
}

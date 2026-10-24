<?php

declare(strict_types=1);

namespace App\Services\Keuangan;

use App\DTOs\Keuangan\AturanDenda;
use App\Enums\JenisItemTagihan;
use App\Enums\StudentStatus;
use App\Exceptions\AturanAkademikException;
use App\Models\Keuangan\Tagihan;
use App\Models\Keuangan\TagihanItem;
use App\Models\Sdm\Staff;
use App\Notifications\Keuangan\DendaDibebaskan;
use App\Services\Notifikasi\Notifier;
use App\Support\Format;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Charging for late payment, and forgiving the charge.
 *
 * A late fee is one line on the invoice (`jenis = denda`) whose amount grows as
 * the days pass, so `tagihan.total` stays the sum of its lines and every screen
 * that reads the total needs no special case. Two properties are load-bearing:
 *
 *  1. **Accrual, not recomputation.** Each run charges only the periods begun
 *     since `denda_dihitung_sampai`, at the principal outstanding *that day*.
 *     A fee recomputed from scratch would shrink after a partial payment, as
 *     if the earlier lateness had not happened; and running twice on one day
 *     would be a question of whether the formula happened to agree with
 *     itself. Here a second run finds zero new periods and does nothing.
 *
 *  2. **A waiver is a line, not a deletion.** Forgiving the fee adds a negative
 *     line naming who and why, and stops accrual for good. The gross fee, the
 *     waiver and the net all remain visible — the same shape as a scholarship.
 *
 * Fees stop growing once the principal is settled; a student who paid tuition
 * in full but still owes the fee is not getting later.
 */
class DendaService
{
    public const NAMA_PEMBEBASAN = 'Pembebasan denda keterlambatan';

    public function __construct(
        private readonly PembayaranService $pembayaran,
        private readonly Notifier $notifier,
    ) {}

    /**
     * Unsettled invoices past their grace period, for students still enrolled.
     *
     * Students on leave or already out are not chased for a term they are not
     * attending — the same population the due-date reminder addresses.
     *
     * @return Builder<Tagihan>
     */
    public function kandidat(AturanDenda $aturan, ?Carbon $hari = null): Builder
    {
        $hari = ($hari ?? Carbon::today())->copy()->startOfDay();

        $batas = $hari->copy()->subDays($aturan->tenggangHari)->toDateString();

        return Tagihan::query()
            ->belumLunas()
            ->whereNull('denda_dibebaskan_at')
            // A dispensation extends the due date for fee purposes too; see
            // jatuhTempoEfektif().
            ->where(fn (Builder $q) => $q
                ->where(fn (Builder $tanpa) => $tanpa
                    ->whereNull('dispensasi_sampai')
                    ->whereDate('jatuh_tempo', '<', $batas))
                ->orWhere(fn (Builder $dengan) => $dengan
                    ->whereNotNull('dispensasi_sampai')
                    ->whereDate('dispensasi_sampai', '<', $batas)
                    ->whereDate('jatuh_tempo', '<', $batas)))
            ->whereHas('mahasiswa', fn ($q) => $q->where('status', StudentStatus::Aktif->value));
    }

    /**
     * The date lateness is counted from.
     *
     * A dispensation is the finance office saying "you have until this date".
     * Charging for the days it covered would contradict that in the student's
     * own invoice, so the fee clock starts at whichever is later.
     */
    public function jatuhTempoEfektif(Tagihan $tagihan): Carbon
    {
        $jatuhTempo = $tagihan->jatuh_tempo->copy()->startOfDay();

        if ($tagihan->dispensasi_sampai !== null && $tagihan->dispensasi_sampai->gt($jatuhTempo)) {
            return $tagihan->dispensasi_sampai->copy()->startOfDay();
        }

        return $jatuhTempo;
    }

    /**
     * The fee this invoice would be charged today, without charging it.
     *
     * Zero while the policy is off, inside the grace period, under a live
     * dispensation, after a waiver, once the principal is paid, or once the cap
     * is reached.
     */
    public function hitung(Tagihan $tagihan, AturanDenda $aturan, ?Carbon $hari = null): int
    {
        $hari = ($hari ?? Carbon::today())->copy()->startOfDay();

        if (!$aturan->aktif || $tagihan->dendaDibebaskan() || $tagihan->dispensasiAktif()) {
            return 0;
        }

        $sisaPokok = $tagihan->sisaPokok();

        if ($sisaPokok <= 0) {
            return 0;
        }

        $jatuhTempo = $this->jatuhTempoEfektif($tagihan);

        $periodeKini = $aturan->periodeSampai($jatuhTempo, $hari);
        $periodeLalu = $tagihan->denda_dihitung_sampai === null
            ? 0
            : $aturan->periodeSampai($jatuhTempo, $tagihan->denda_dihitung_sampai);

        $periodeBaru = max(0, $periodeKini - $periodeLalu);

        if ($periodeBaru === 0) {
            return 0;
        }

        $tambahan = $aturan->perPeriode($sisaPokok) * $periodeBaru;

        if ($aturan->maksimum > 0) {
            $tambahan = min($tambahan, max(0, $aturan->maksimum - $this->dendaKotor($tagihan)));
        }

        return max(0, $tambahan);
    }

    /**
     * Charges today's fee on one invoice and moves its watermark.
     *
     * Returns what was added. Idempotent per day: the watermark is advanced
     * even when nothing was charged, so a second run on the same day has no
     * new periods to bill.
     */
    public function terapkan(Tagihan $tagihan, AturanDenda $aturan, ?Carbon $hari = null): int
    {
        $hari = ($hari ?? Carbon::today())->copy()->startOfDay();

        return DB::transaction(function () use ($tagihan, $aturan, $hari): int {
            /** @var Tagihan $terkunci */
            $terkunci = Tagihan::whereKey($tagihan->id)->lockForUpdate()->firstOrFail();

            $tambahan = $this->hitung($terkunci, $aturan, $hari);

            if ($tambahan > 0) {
                $item = $terkunci->itemDenda()->first() ?? TagihanItem::create([
                    'tagihan_id' => $terkunci->id,
                    'jenis' => JenisItemTagihan::Denda,
                    'nama' => __('keuangan.denda.nama_baris'),
                    'nominal' => 0,
                ]);

                $item->update(['nominal' => (int) $item->nominal + $tambahan]);

                $terkunci->update(['denda' => (int) $terkunci->denda + $tambahan]);
                $this->hitungUlangTotal($terkunci);

                $terkunci->recordActivity('late_fee_applied', sprintf(
                    'Denda keterlambatan %s ditambahkan (total denda %s) per %s.',
                    Format::rupiah($tambahan),
                    Format::rupiah((int) $terkunci->fresh()->denda),
                    Format::tanggal($hari),
                ));
            }

            // Only ever forward. A run for yesterday after today's has already
            // happened must not re-open periods that were billed.
            if ($terkunci->denda_dihitung_sampai === null || $terkunci->denda_dihitung_sampai->lt($hari)) {
                $terkunci->update(['denda_dihitung_sampai' => $hari]);
            }

            return $tambahan;
        });
    }

    /**
     * The nightly run over every candidate invoice.
     *
     * @return array{diperiksa: int, dikenai: int, total_rupiah: int}
     */
    public function jalankan(?AturanDenda $aturan = null, ?Carbon $hari = null, bool $kering = false): array
    {
        $aturan ??= AturanDenda::dariConfig();
        $hari = ($hari ?? Carbon::today())->copy()->startOfDay();

        $hasil = ['diperiksa' => 0, 'dikenai' => 0, 'total_rupiah' => 0];

        if (!$aturan->aktif) {
            return $hasil;
        }

        $this->kandidat($aturan, $hari)
            ->with('itemDenda')
            ->chunkById(200, function ($daftar) use ($aturan, $hari, $kering, &$hasil): void {
                foreach ($daftar as $tagihan) {
                    $hasil['diperiksa']++;

                    // Per invoice, not one transaction for the run: a locked row
                    // must not roll back four thousand correct charges.
                    $tambahan = $kering
                        ? $this->hitung($tagihan, $aturan, $hari)
                        : $this->terapkan($tagihan, $aturan, $hari);

                    if ($tambahan > 0) {
                        $hasil['dikenai']++;
                        $hasil['total_rupiah'] += $tambahan;
                    }
                }
            });

        return $hasil;
    }

    /**
     * Forgives the fee on one invoice, for a stated reason, and stops accrual.
     *
     * The reason is mandatory for the same reason it is on a discretionary
     * reduction: lowering what a student owes without a written justification
     * is indistinguishable from doing a favour.
     */
    public function bebaskan(Tagihan $tagihan, Staff $staff, string $alasan): TagihanItem
    {
        if (blank($alasan)) {
            throw new AturanAkademikException(__('keuangan.denda.alasan_wajib'));
        }

        if ($tagihan->dendaDibebaskan()) {
            throw new AturanAkademikException('Denda pada tagihan ini sudah dibebaskan.');
        }

        $denda = (int) $tagihan->denda;

        if ($denda <= 0) {
            throw new AturanAkademikException('Tagihan ini tidak memiliki denda yang dapat dibebaskan.');
        }

        $item = DB::transaction(function () use ($tagihan, $staff, $alasan, $denda): TagihanItem {
            $item = TagihanItem::create([
                'tagihan_id' => $tagihan->id,
                'jenis' => JenisItemTagihan::Potongan,
                'nama' => self::NAMA_PEMBEBASAN,
                'nominal' => -$denda,
                'alasan' => $alasan,
                'diputus_by_staff_id' => $staff->id,
                'diputus_at' => now(),
            ]);

            $tagihan->update([
                'denda' => 0,
                'denda_dibebaskan_at' => now(),
            ]);

            $this->hitungUlangTotal($tagihan);

            $tagihan->recordActivity('late_fee_waived', sprintf(
                'Denda keterlambatan %s dibebaskan oleh %s. Alasan: %s',
                Format::rupiah($denda),
                $staff->nama,
                $alasan,
            ));

            return $item;
        });

        $this->notifier->kirim($tagihan->mahasiswa, new DendaDibebaskan($tagihan->refresh(), $item));

        return $item;
    }

    /** The fee before any waiver — what the cap is measured against. */
    private function dendaKotor(Tagihan $tagihan): int
    {
        return (int) $tagihan->item()->denda()->sum('nominal');
    }

    /**
     * Rewrites `total` from the lines, then re-derives the payment status —
     * the same two steps PotonganService takes, for the same reason: an
     * invoice that was paid in full before a fee landed is now partly paid,
     * and one whose fee was forgiven may be settled again.
     */
    private function hitungUlangTotal(Tagihan $tagihan): void
    {
        $total = (int) TagihanItem::where('tagihan_id', $tagihan->id)->sum('nominal');

        $tagihan->update(['total' => max(0, $total)]);

        $this->pembayaran->hitungUlang($tagihan);
    }
}

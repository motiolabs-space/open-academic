<?php

declare(strict_types=1);

namespace App\Services\Keuangan;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\AturanAkademikException;
use App\Models\Keuangan\Pembayaran;
use App\Models\Keuangan\Tagihan;
use App\Models\Sdm\Staff;
use App\Notifications\Keuangan\PembayaranDiterima;
use App\Services\Notifikasi\Notifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Recording money against a debt.
 *
 * The one invariant: `tagihan.terbayar` is the sum of that invoice's settled
 * payments, always. Every screen in the system reads it — the KRS gate reads it
 * to decide whether a student may register, and the graduation checklist reads
 * it to decide whether somebody may graduate. A drift of one rupiah in either
 * direction is a student wrongly blocked or wrongly cleared.
 *
 * So the total is recomputed from the payment rows rather than incremented, and
 * it happens inside a row lock.
 */
class PembayaranService
{
    public function __construct(private readonly Notifier $notifier) {}

    /**
     * Records a payment taken at the counter.
     *
     * Cash and bank transfers still exist, and a system that only understands
     * gateway callbacks forces the finance office to keep a parallel ledger in
     * a spreadsheet.
     */
    public function catatManual(
        Tagihan $tagihan,
        int $nominal,
        Staff $staff,
        string $channel = 'tunai',
        ?string $referensi = null,
    ): Pembayaran {
        if ($nominal <= 0) {
            throw new AturanAkademikException('Nominal pembayaran harus lebih dari nol.');
        }

        $sisa = (int) $tagihan->total - (int) $tagihan->terbayar;

        // Overpayment is almost always a typo — an extra zero on a transfer
        // amount — and letting it through produces a negative balance that
        // every downstream screen has to special-case.
        if ($nominal > $sisa) {
            throw new AturanAkademikException(sprintf(
                'Nominal melebihi sisa tagihan. Sisa: Rp%s, dimasukkan: Rp%s.',
                number_format($sisa, 0, ',', '.'),
                number_format($nominal, 0, ',', '.'),
            ));
        }

        $pembayaran = DB::transaction(function () use ($tagihan, $nominal, $staff, $channel, $referensi): Pembayaran {
            $pembayaran = Pembayaran::create([
                'tagihan_id' => $tagihan->id,
                'mahasiswa_id' => $tagihan->mahasiswa_id,
                'nomor_transaksi' => $referensi ?: 'MAN-'.Str::upper(Str::random(12)),
                'gateway' => 'manual',
                'channel' => $channel,
                'nominal' => $nominal,
                'status' => PaymentStatus::Settlement,
                'paid_at' => now(),
            ]);

            $this->hitungUlang($tagihan);

            $tagihan->recordActivity(
                'payment_recorded',
                sprintf(
                    'Pembayaran %s Rp%s dicatat oleh %s.',
                    $channel,
                    number_format($nominal, 0, ',', '.'),
                    $staff->nama,
                ),
            );

            return $pembayaran;
        });

        // The receipt. Worth sending even for cash handed over at a counter:
        // it is the student's evidence that the office recorded what they paid.
        $this->notifier->kirim($tagihan->mahasiswa, new PembayaranDiterima($pembayaran->refresh()));

        return $pembayaran;
    }

    /**
     * Reverses a payment that should not have been recorded.
     *
     * Never deleted — the row stays with status Refund so the trail shows both
     * that money was recorded and that it was taken back. A finance record that
     * can vanish is not a record.
     */
    public function batalkan(Pembayaran $pembayaran, Staff $staff, string $alasan): Pembayaran
    {
        if (blank($alasan)) {
            throw new AturanAkademikException('Pembatalan pembayaran wajib disertai alasan.');
        }

        if ($pembayaran->status !== PaymentStatus::Settlement) {
            throw new AturanAkademikException('Hanya pembayaran berstatus berhasil yang dapat dibatalkan.');
        }

        DB::transaction(function () use ($pembayaran, $staff, $alasan): void {
            $pembayaran->update(['status' => PaymentStatus::Refund]);

            $this->hitungUlang($pembayaran->tagihan);

            $pembayaran->tagihan->recordActivity(
                'payment_reversed',
                sprintf(
                    'Pembayaran Rp%s dibatalkan oleh %s. Alasan: %s',
                    number_format((float) $pembayaran->nominal, 0, ',', '.'),
                    $staff->nama,
                    $alasan,
                ),
            );
        });

        return $pembayaran->refresh();
    }

    /**
     * Recomputes an invoice's paid total from its settled payments.
     *
     * Derived, never incremented. An increment that runs twice — a retried job,
     * a double-submitted form — silently marks a debt paid that is not.
     */
    public function hitungUlang(Tagihan $tagihan): Tagihan
    {
        return DB::transaction(function () use ($tagihan): Tagihan {
            $terkunci = Tagihan::whereKey($tagihan->id)->lockForUpdate()->firstOrFail();

            $terbayar = (int) Pembayaran::query()
                ->where('tagihan_id', $terkunci->id)
                ->where('status', PaymentStatus::Settlement->value)
                ->sum('nominal');

            $terkunci->update([
                'terbayar' => $terbayar,
                'status' => $this->statusUntuk($terkunci, $terbayar),
            ]);

            return $terkunci;
        });
    }

    private function statusUntuk(Tagihan $tagihan, int $terbayar): InvoiceStatus
    {
        $total = (int) $tagihan->total;

        return match (true) {
            $terbayar >= $total && $total > 0 => InvoiceStatus::Lunas,
            $terbayar > 0 => InvoiceStatus::Sebagian,
            default => InvoiceStatus::BelumBayar,
        };
    }
}

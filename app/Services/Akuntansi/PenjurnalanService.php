<?php

declare(strict_types=1);

namespace App\Services\Akuntansi;

use App\Enums\JenisDokumenAkuntansi;
use App\Enums\StatusDokumenAkuntansi;
use App\Models\Akuntansi\DokumenAkuntansi;
use App\Models\Keuangan\Pembayaran;
use App\Models\Keuangan\Tagihan;
use App\Models\Keuangan\TagihanItem;
use App\Support\Akuntansi;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Turning money events into documents the accounting system will accept.
 *
 * **Opt-in.** With `AKUNTANSI_DRIVER=nonaktif` — the default — every method here
 * returns immediately and nothing is recorded at all. A campus keeping its
 * ledger elsewhere carries no cost for a bridge it does not use, and billing
 * behaves exactly as it did before this module existed.
 *
 * Nothing here touches the network. Every method writes a row to the outbox and
 * returns; `PengirimAkuntansi` posts them later. That separation is the point:
 * issuing invoices for five thousand students must not wait on five thousand
 * HTTP calls, and an accounting system that is down must not be able to fail a
 * billing run. The debt exists whether or not anybody managed to book it.
 *
 * Failures here are swallowed and logged, for the same reason the Notifier
 * swallows its own: **recording an event in the ledger must never be able to
 * undo the event.** A student paid; if queueing the journal throws, the right
 * outcome is a logged failure and a document a person can requeue, not a
 * rolled-back receipt.
 */
class PenjurnalanService
{
    /**
     * An invoice was issued.
     *
     * Two documents when the invoice carries a discount, and this is where the
     * campus's chosen treatment shows up:
     *
     *   **bruto** — the invoice is the full tariff, and a second journal moves
     *   the discount to Beban Beasiswa. Revenue is what the campus charged;
     *   the expense is what it gave away. A foundation asking "how much did we
     *   spend on scholarships" gets an answer.
     *
     *   **netto** — one invoice for what is actually owed. Simpler, and the
     *   scholarship vanishes: the books show smaller revenue with no cause.
     *
     * See config('akuntansi.perlakuan.beasiswa').
     */
    public function tagihanTerbit(Tagihan $tagihan): void
    {
        $this->catat(function () use ($tagihan): void {
            $bruto = config('akuntansi.perlakuan.beasiswa') === 'bruto';
            $nominal = $bruto ? $tagihan->totalKotor() : (int) $tagihan->total;

            if ($nominal <= 0) {
                // A fully waived invoice is a real thing (see PotonganService),
                // but an invoice for nothing is not a document any ledger wants.
                // The waiver itself is still booked, below.
                $nominal = 0;
            }

            if ($nominal > 0) {
                $this->antre(
                    JenisDokumenAkuntansi::Invoice,
                    'oa-inv-'.$tagihan->uuid,
                    $nominal,
                    [
                        'kontak_mahasiswa' => $tagihan->mahasiswa->uuid,
                        'amount' => $nominal,
                        'taxable' => (bool) config('akuntansi.perlakuan.kena_ppn'),
                        'description' => $tagihan->keterangan,
                        'transaction_date' => $tagihan->created_at?->toDateString() ?? now()->toDateString(),
                        'due_date' => $tagihan->jatuh_tempo->toDateString(),
                        'reference_number' => $tagihan->nomor,
                    ],
                    $tagihan,
                );
            }

            if ($bruto) {
                foreach ($tagihan->item()->where('nominal', '<', 0)->get() as $item) {
                    $this->potonganDiberikan($item);
                }
            }
        }, 'tagihan '.$tagihan->nomor);
    }

    /**
     * A discount or scholarship reduced what a student owes.
     *
     * Dr Beban Beasiswa, Cr Piutang — so receivable falls to what is actually
     * collectible while revenue stays at the full tariff.
     *
     * Keyed on the invoice line rather than on the amount. A student can be
     * granted two waivers of exactly the same size in one term, and keying on
     * the number would silently swallow the second as a duplicate of the first
     * — the campus would have given the money away and never booked it.
     */
    public function potonganDiberikan(TagihanItem $item): void
    {
        $this->catat(function () use ($item): void {
            if (config('akuntansi.perlakuan.beasiswa') !== 'bruto') {
                // Under netto the invoice already carries the reduced amount;
                // a second document would take it off twice.
                return;
            }

            $nominal = abs((int) $item->nominal);

            if ($nominal <= 0) {
                return;
            }

            $akun = config('akuntansi.akun');
            $tagihan = $item->tagihan;

            $this->antre(
                JenisDokumenAkuntansi::Jurnal,
                'oa-diskon-'.$item->id,
                $nominal,
                [
                    'transaction_date' => ($item->created_at ?? now())->toDateString(),
                    'description' => trim($item->nama.' — '.$tagihan->nomor),
                    'reference_number' => $tagihan->nomor,
                    'entries' => [
                        ['account_code' => $akun['beban_beasiswa'], 'debit' => $nominal, 'credit' => 0],
                        ['account_code' => $akun['piutang'], 'debit' => 0, 'credit' => $nominal],
                    ],
                ],
                $tagihan,
            );
        }, 'potongan item '.$item->id);
    }

    /**
     * A payment was received.
     *
     * Dr Kas/Bank, Cr Piutang, posted as a journal because easyERP's v1 API has
     * no payment endpoint yet — its own docs list one as planned. The
     * consequence is stated rather than hidden: this settles the ledger but
     * does not flip the invoice's status on that side, so "lunas" is only true
     * in Open Academic until that endpoint exists.
     */
    public function pembayaranDiterima(Pembayaran $pembayaran): void
    {
        $this->catat(function () use ($pembayaran): void {
            $akun = config('akuntansi.akun');
            $nominal = (int) $pembayaran->nominal;

            if ($nominal <= 0) {
                return;
            }

            $kas = in_array($pembayaran->channel, (array) config('akuntansi.channel_kas'), true)
                ? $akun['kas']
                : $akun['bank'];

            $this->antre(
                JenisDokumenAkuntansi::Jurnal,
                'oa-bayar-'.$pembayaran->uuid,
                $nominal,
                [
                    'transaction_date' => ($pembayaran->paid_at ?? $pembayaran->created_at ?? now())->toDateString(),
                    'description' => 'Penerimaan pembayaran — '.$pembayaran->tagihan->nomor,
                    'reference_number' => $pembayaran->nomor_transaksi,
                    'entries' => [
                        ['account_code' => $kas, 'debit' => $nominal, 'credit' => 0],
                        ['account_code' => $akun['piutang'], 'debit' => 0, 'credit' => $nominal],
                    ],
                ],
                $pembayaran,
            );
        }, 'pembayaran '.$pembayaran->nomor_transaksi);
    }

    /**
     * A payment was voided.
     *
     * A reversing entry rather than a deletion. The original receipt happened
     * and was booked; erasing it would leave a gap in a numbered sequence and
     * an audit trail that cannot explain itself. Reversal is what accounting
     * does with a mistake, and it is also the only option that works once the
     * far side has closed the period.
     */
    public function pembayaranDibatalkan(Pembayaran $pembayaran): void
    {
        $this->catat(function () use ($pembayaran): void {
            $akun = config('akuntansi.akun');
            $nominal = (int) $pembayaran->nominal;

            if ($nominal <= 0) {
                return;
            }

            $kas = in_array($pembayaran->channel, (array) config('akuntansi.channel_kas'), true)
                ? $akun['kas']
                : $akun['bank'];

            $this->antre(
                JenisDokumenAkuntansi::Jurnal,
                'oa-batal-'.$pembayaran->uuid,
                $nominal,
                [
                    'transaction_date' => now()->toDateString(),
                    'description' => 'Pembatalan pembayaran — '.$pembayaran->nomor_transaksi,
                    'reference_number' => $pembayaran->nomor_transaksi,
                    'entries' => [
                        ['account_code' => $akun['piutang'], 'debit' => $nominal, 'credit' => 0],
                        ['account_code' => $kas, 'debit' => 0, 'credit' => $nominal],
                    ],
                ],
                $pembayaran,
            );
        }, 'pembatalan '.$pembayaran->nomor_transaksi);
    }

    /**
     * Writes one document to the outbox.
     *
     * The unique index on the idempotency key does double duty: it is sent as
     * the `Idempotency-Key` header, and it stops the same event being queued
     * twice locally. A billing run re-executed after a crash therefore adds
     * nothing.
     *
     * @param array<string, mixed> $payload
     */
    private function antre(
        JenisDokumenAkuntansi $jenis,
        string $kunci,
        int $nominal,
        array $payload,
        ?object $sumber = null,
    ): ?DokumenAkuntansi {
        // Every journal this class writes must balance before it is queued.
        // easyERP would refuse a lopsided one anyway, but discovering that at
        // send time turns a coding mistake into a queue full of failures.
        if ($jenis === JenisDokumenAkuntansi::Jurnal) {
            $this->pastikanSeimbang($payload['entries'] ?? []);
        }

        try {
            return DokumenAkuntansi::create([
                'jenis' => $jenis,
                'lokal_type' => $sumber === null ? null : $sumber::class,
                'lokal_id' => $sumber?->getKey(),
                'kunci_idempotensi' => $kunci,
                'payload' => $payload,
                'nominal' => $nominal,
                'status' => StatusDokumenAkuntansi::Menunggu,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Already queued. Not an error — this is the guard working.
            return null;
        }
    }

    /**
     * @param array<int, array<string, mixed>> $entries
     */
    private function pastikanSeimbang(array $entries): void
    {
        $debit = array_sum(array_column($entries, 'debit'));
        $kredit = array_sum(array_column($entries, 'credit'));

        if ($debit !== $kredit) {
            throw new \LogicException(sprintf(
                'Jurnal tidak seimbang: debit %d, kredit %d.',
                $debit,
                $kredit,
            ));
        }
    }

    /**
     * Records one mapping — if recording is switched on, and without ever
     * letting its failure escape.
     *
     * The single choke point for both rules, which is why every public method
     * above goes through it.
     *
     * **Opt-in.** With `AKUNTANSI_DRIVER=nonaktif` nothing runs at all: no
     * queries, no outbox rows, no cost. A campus that keeps its ledger
     * elsewhere should not accumulate documents nobody will ever send.
     *
     * **Failure-proof.** See the class docblock: the money event already
     * happened, and booking it must never be able to unmake it.
     */
    private function catat(callable $kerja, string $konteks): void
    {
        if (!Akuntansi::aktif()) {
            return;
        }

        try {
            $kerja();
        } catch (Throwable $e) {
            Log::error('Gagal mengantre dokumen akuntansi.', [
                'konteks' => $konteks,
                'galat' => $e->getMessage(),
            ]);

            report($e);
        }
    }
}

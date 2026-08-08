<?php

declare(strict_types=1);

namespace App\Services\Keuangan;

use App\Enums\JenisItemTagihan;
use App\Exceptions\AturanAkademikException;
use App\Models\Keuangan\Beasiswa;
use App\Models\Keuangan\BeasiswaPenerima;
use App\Models\Keuangan\Tagihan;
use App\Models\Keuangan\TagihanItem;
use App\Models\Sdm\Staff;
use App\Notifications\Keuangan\PotonganDiberikan;
use App\Services\Akuntansi\PenjurnalanService;
use App\Services\Notifikasi\Notifier;
use Illuminate\Support\Facades\DB;

/**
 * Lowering what a student owes.
 *
 * A reduction is a negative line on the invoice, not a number netted off
 * somewhere else. `tagihan.total` stays the sum of its lines and therefore stays
 * what the student actually owes — which matters because ten other places read
 * that column: the KRS payment gate, the graduation checklist, the arrears
 * reminder, the certificate-of-enrolment rule, the dashboards. None of them
 * needed changing, and none of them can drift out of agreement with it.
 *
 * Three things can go wrong here and each of them loses money quietly.
 */
class PotonganService
{
    public function __construct(
        private readonly PembayaranService $pembayaran,
        private readonly Notifier $notifier,
        private readonly PenjurnalanService $penjurnalan,
    ) {}

    /**
     * Applies every reduction the student is entitled to, and recomputes.
     *
     * Idempotent: existing award-derived lines are replaced rather than added
     * to. The alternative — appending on each run — turns a re-issued invoice or
     * a retried job into a second scholarship, and the totals still balance, so
     * nothing looks wrong.
     */
    public function terapkan(Tagihan $tagihan, ?Staff $staff = null): Tagihan
    {
        $tagihan->loadMissing(['item', 'mahasiswa', 'tahunAkademik']);

        $penerima = BeasiswaPenerima::query()
            ->with(['beasiswa', 'mulai', 'selesai'])
            ->where('mahasiswa_id', $tagihan->mahasiswa_id)
            ->aktif()
            ->get()
            ->filter(fn (BeasiswaPenerima $p): bool => $p->mencakupTerm($tagihan->tahunAkademik)
                && $p->beasiswa->is_active);

        DB::transaction(function () use ($tagihan, $penerima, $staff): void {
            // Clear award-derived lines only. A discretionary waiver was a human
            // decision about this invoice and is not ours to undo.
            $tagihan->item()->potongan()->whereNotNull('beasiswa_penerima_id')->delete();

            foreach ($penerima as $satu) {
                $nominal = $this->hitungPotongan($tagihan->fresh('item'), $satu->beasiswa);

                if ($nominal <= 0) {
                    continue;
                }

                TagihanItem::create([
                    'tagihan_id' => $tagihan->id,
                    'jenis' => JenisItemTagihan::Potongan,
                    'beasiswa_penerima_id' => $satu->id,
                    'nama' => 'Beasiswa '.$satu->beasiswa->nama,
                    'nominal' => -$nominal,
                    'alasan' => $satu->beasiswa->penyandang !== null
                        ? 'Ditanggung '.$satu->beasiswa->penyandang
                        : null,
                    'diputus_by_staff_id' => $staff?->id,
                    'diputus_at' => now(),
                ]);
            }

            $this->hitungUlangTotal($tagihan);
        });

        return $tagihan->refresh();
    }

    /**
     * A one-off reduction decided for this student, on this invoice.
     *
     * Separate from a scholarship because it has no scheme behind it — which is
     * exactly why the reason is mandatory. A discretionary reduction without a
     * written justification is indistinguishable from someone lowering a bill
     * for a friend, and it is the single highest-value fraudulent action this
     * system permits.
     */
    public function keringanan(
        Tagihan $tagihan,
        int $nominal,
        string $alasan,
        Staff $staff,
    ): TagihanItem {
        if ($nominal <= 0) {
            throw new AturanAkademikException('Nominal keringanan harus lebih dari nol.');
        }

        if (blank($alasan)) {
            throw new AturanAkademikException(
                'Keringanan wajib disertai alasan. Potongan tanpa alasan tertulis tidak dapat '
                    .'dibedakan dari penyalahgunaan.',
            );
        }

        $tagihan->loadMissing('item');
        $sisaDapatDipotong = $this->sisaDapatDipotong($tagihan);

        /*
         * Never below zero.
         *
         * `tagihan.total` is unsigned and every downstream reader assumes a debt
         * or nothing. A negative total would be read as an enormous positive one
         * by the database and as a credit by nobody.
         */
        if ($nominal > $sisaDapatDipotong) {
            throw new AturanAkademikException(sprintf(
                'Potongan %s melebihi sisa tagihan yang dapat dipotong (%s). '
                    .'Total tagihan tidak boleh menjadi negatif.',
                'Rp'.number_format($nominal, 0, ',', '.'),
                'Rp'.number_format($sisaDapatDipotong, 0, ',', '.'),
            ));
        }

        $item = DB::transaction(function () use ($tagihan, $nominal, $alasan, $staff): TagihanItem {
            $item = TagihanItem::create([
                'tagihan_id' => $tagihan->id,
                'jenis' => JenisItemTagihan::Potongan,
                'nama' => 'Keringanan',
                'nominal' => -$nominal,
                'alasan' => $alasan,
                'diputus_by_staff_id' => $staff->id,
                'diputus_at' => now(),
            ]);

            $this->hitungUlangTotal($tagihan);

            $tagihan->recordActivity('discount_granted', sprintf(
                'Keringanan Rp%s diberikan oleh %s. Alasan: %s',
                number_format($nominal, 0, ',', '.'),
                $staff->nama,
                $alasan,
            ));

            return $item;
        });

        $this->notifier->kirim($tagihan->mahasiswa, new PotonganDiberikan($tagihan->refresh()));

        /*
         * Dr Beban Beasiswa, Cr Piutang.
         *
         * Only for waivers granted after the invoice was issued. The ones
         * applied at issue time are journalled by PenerbitanTagihanService,
         * which sees the whole invoice at once — and the idempotency key is the
         * line's own id, so neither path can book the same waiver twice.
         */
        $this->penjurnalan->potonganDiberikan($item->refresh()->load('tagihan'));

        return $item;
    }

    /** Removes a reduction and puts the amount back on the bill. */
    public function hapus(TagihanItem $item, Staff $staff, string $alasan): Tagihan
    {
        if ($item->jenis !== JenisItemTagihan::Potongan) {
            throw new AturanAkademikException('Hanya baris potongan yang dapat dihapus dari tagihan.');
        }

        if (blank($alasan)) {
            throw new AturanAkademikException('Pembatalan potongan wajib disertai alasan.');
        }

        $tagihan = $item->tagihan;

        DB::transaction(function () use ($item, $tagihan, $staff, $alasan): void {
            $tagihan->recordActivity('discount_removed', sprintf(
                'Potongan %s (Rp%s) dihapus oleh %s. Alasan: %s',
                $item->nama,
                number_format($item->besaran(), 0, ',', '.'),
                $staff->nama,
                $alasan,
            ));

            $item->delete();

            $this->hitungUlangTotal($tagihan);
        });

        return $tagihan->refresh();
    }

    /**
     * Rewrites `total` from the invoice's own lines, then re-derives the
     * payment status.
     *
     * The second step is the one that is easy to forget. A student who had paid
     * in full before a reduction landed is still paid in full afterwards, and an
     * invoice left marked "partly paid" would block their study plan and appear
     * on the arrears reminder for a debt that no longer exists.
     */
    private function hitungUlangTotal(Tagihan $tagihan): void
    {
        $total = (int) TagihanItem::where('tagihan_id', $tagihan->id)->sum('nominal');

        $tagihan->update(['total' => max(0, $total)]);

        // Derives `terbayar` and `status` together, from the payment rows.
        $this->pembayaran->hitungUlang($tagihan);
    }

    /**
     * How much of this invoice can still be discounted.
     *
     * Charges minus reductions already applied. Zero once the bill is fully
     * covered.
     */
    public function sisaDapatDipotong(Tagihan $tagihan): int
    {
        $kotor = (int) $tagihan->item()->tagihan()->sum('nominal');
        $potongan = abs((int) $tagihan->item()->potongan()->sum('nominal'));

        return max(0, $kotor - $potongan);
    }

    /**
     * What one scheme takes off this invoice.
     *
     * Percent schemes apply to the charge lines they cover; fixed schemes pay a
     * stated amount, capped at what is actually owed. Both are then capped again
     * at what remains after other reductions — two scholarships covering 60% and
     * 60% of the same fee must not total 120%.
     */
    private function hitungPotongan(Tagihan $tagihan, Beasiswa $beasiswa): int
    {
        $dicakup = (int) $tagihan->item()->tagihan()->get()
            ->filter(fn (TagihanItem $i): bool => $beasiswa->mencakup((string) $i->nama))
            ->sum('nominal');

        if ($dicakup <= 0) {
            return 0;
        }

        $kasar = $beasiswa->persen !== null
            ? (int) round($dicakup * $beasiswa->persen / 100)
            : min((int) $beasiswa->nominal, $dicakup);

        return min($kasar, $this->sisaDapatDipotong($tagihan));
    }
}

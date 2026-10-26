<?php

declare(strict_types=1);

namespace App\Services\Pmb;

use App\DTOs\Pmb\PembayaranFormulirData;
use App\Enums\ApplicantStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\AturanAkademikException;
use App\Models\Pmb\PmbPembayaran;
use App\Models\Pmb\PmbPendaftar;
use App\Models\Sdm\Staff;
use App\Notifications\Pmb\PembayaranFormulirDiterima;
use App\Services\Notifikasi\Notifier;
use App\Support\Format;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The form fee, recorded by hand at the cashier (ADR-014, ADR-024).
 *
 * Kept out of `tagihan` on purpose. An invoice there belongs to a student and
 * a term — one per pair, by unique index — and everything that reads it
 * assumes so: late-fee accrual, due-date reminders, the KRS gate, the
 * graduation checklist, the accounting outbox's customer. An applicant is none
 * of those things, and a form fee must never accrue a late fee.
 *
 * The rules are the ones PembayaranService keeps for tuition: no
 * overpayment, a reversal keeps its row, and "paid" is derived from the
 * settled rows rather than incremented.
 */
class PembayaranFormulirService
{
    public function __construct(
        private readonly PendaftaranPmbService $pendaftaran,
        private readonly Notifier $notifier,
    ) {}

    /**
     * Applications submitted and waiting for their fee, oldest first — the
     * order the cashier should match transfers in.
     *
     * Only the transfer receipt is loaded among the documents: this list is
     * read by the finance office, which has no business with the rest.
     */
    public function antrean(string $cari = ''): LengthAwarePaginator
    {
        return PmbPendaftar::query()
            ->with(['gelombang', 'pembayaran', 'berkas' => fn ($q) => $q->where('jenis', 'bukti_bayar')])
            ->where('status', ApplicantStatus::Mendaftar->value)
            ->where('biaya_formulir', '>', 0)
            ->cari($cari, ['nama', 'nomor_pendaftaran', 'email'])
            ->orderBy('diajukan_at')
            ->paginate(25)
            ->withQueryString();
    }

    /** @return Collection<int, PmbPembayaran> */
    public function terakhir(int $batas = 15): Collection
    {
        return PmbPembayaran::query()
            ->with(['pendaftar', 'pencatat'])
            ->latest('id')
            ->limit($batas)
            ->get();
    }

    public function catat(PmbPendaftar $pendaftar, PembayaranFormulirData $data, Staff $staff): PmbPembayaran
    {
        if (in_array($pendaftar->status, [ApplicantStatus::Draf, ApplicantStatus::Batal], true)) {
            throw new AturanAkademikException(
                'Pembayaran formulir hanya dicatat untuk pendaftaran yang sudah diajukan dan tidak dibatalkan.',
            );
        }

        if ($data->nominal <= 0) {
            throw new AturanAkademikException('Nominal pembayaran harus lebih dari nol.');
        }

        $pembayaran = DB::transaction(function () use ($pendaftar, $data, $staff): PmbPembayaran {
            $terkunci = PmbPendaftar::whereKey($pendaftar->id)->lockForUpdate()->firstOrFail();
            $sisa = $terkunci->sisaFormulir();

            if ($data->nominal > $sisa) {
                throw new AturanAkademikException(sprintf(
                    'Nominal melebihi sisa biaya formulir. Sisa: %s, dimasukkan: %s.',
                    Format::rupiah($sisa),
                    Format::rupiah($data->nominal),
                ));
            }

            $pembayaran = PmbPembayaran::create([
                'pmb_pendaftar_id' => $terkunci->id,
                'nominal' => $data->nominal,
                'channel' => $data->channel,
                'referensi' => $data->referensi ?: 'PMB-'.Str::upper(Str::random(10)),
                'status' => PaymentStatus::Settlement,
                'dicatat_oleh_id' => $staff->id,
                'paid_at' => now(),
            ]);

            $terkunci->unsetRelation('pembayaran');

            if ($terkunci->formulirLunas() && $terkunci->status === ApplicantStatus::Mendaftar) {
                $this->pendaftaran->pindahkan($terkunci, ApplicantStatus::Verifikasi);
            }

            return $pembayaran;
        });

        $pendaftar->refresh();

        // Documents may all have been accepted before the money arrived.
        $this->pendaftaran->periksaKesiapanSeleksi($pendaftar);

        $this->notifier->kirim($pendaftar->akun, new PembayaranFormulirDiterima($pembayaran->refresh()));

        return $pembayaran;
    }

    /**
     * Reverses a recorded payment, keeping the row.
     *
     * Allowed only while the application is still waiting on the desk. Once
     * an applicant has been let into selection on the strength of a payment,
     * taking the payment back is a conversation, not a button.
     */
    public function batalkan(PmbPembayaran $pembayaran, Staff $staff, string $alasan): PmbPembayaran
    {
        if (blank($alasan)) {
            throw new AturanAkademikException('Pembatalan pembayaran wajib disertai alasan.');
        }

        if ($pembayaran->status !== PaymentStatus::Settlement) {
            throw new AturanAkademikException('Hanya pembayaran berstatus berhasil yang dapat dibatalkan.');
        }

        $pendaftar = $pembayaran->pendaftar;

        if (!in_array($pendaftar->status, [ApplicantStatus::Mendaftar, ApplicantStatus::Verifikasi], true)) {
            throw new AturanAkademikException(
                'Pendaftar ini sudah melewati verifikasi; pembayaran formulirnya tidak lagi dapat dibatalkan dari layar ini.',
            );
        }

        DB::transaction(function () use ($pembayaran, $pendaftar, $staff, $alasan): void {
            $pembayaran->update([
                'status' => PaymentStatus::Refund,
                'dibatalkan_at' => now(),
                'alasan_batal' => $alasan,
            ]);

            $pendaftar->unsetRelation('pembayaran');

            if ($pendaftar->status === ApplicantStatus::Verifikasi && !$pendaftar->formulirLunas()) {
                $this->pendaftaran->pindahkan($pendaftar, ApplicantStatus::Mendaftar);
            }

            $pendaftar->recordActivity('payment_reversed', sprintf(
                'Pembayaran formulir %s dibatalkan oleh %s. Alasan: %s',
                Format::rupiah($pembayaran->nominal),
                $staff->nama,
                $alasan,
            ));
        });

        return $pembayaran->refresh();
    }
}

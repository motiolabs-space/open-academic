<?php

declare(strict_types=1);

namespace App\Services\Pmb;

use App\Enums\ApplicantStatus;
use App\Models\Pmb\PmbGelombang;
use App\Models\Pmb\PmbPendaftar;
use App\Notifications\Pmb\HasilSeleksiDiumumkan;
use App\Services\Notifikasi\Notifier;

/**
 * When an applicant learns the outcome.
 *
 * Deciding and announcing are separate on purpose. A selection committee
 * decides over days, corrects itself, and settles the list; applicants who
 * watched their status flip from "lulus" to "tidak lulus" during that week
 * would have been told two things, and would hold the campus to the first.
 * So nothing is visible until the wave is announced, and then everyone
 * decided so far is told at once — by email and on the portal.
 *
 * A decision taken after the announcement — a late interview, a correction —
 * is sent when it is taken. Each distinct outcome is sent once per applicant
 * (Notifier::kirimSekali), so announcing twice, or a correction followed by a
 * re-announcement, does not resend what was already said.
 */
class PengumumanPmbService
{
    public function __construct(private readonly Notifier $notifier) {}

    /** @return int applicants told */
    public function umumkan(PmbGelombang $gelombang): int
    {
        if ($gelombang->diumumkan_at === null) {
            $gelombang->update(['diumumkan_at' => now()]);
            $gelombang->recordActivity('results_announced', "Hasil seleksi {$gelombang->nama} diumumkan.");
        }

        $diberitahu = 0;

        PmbPendaftar::query()
            ->with(['akun', 'gelombang', 'prodiDiterima'])
            ->where('pmb_gelombang_id', $gelombang->id)
            ->whereNotNull('pmb_akun_id')
            ->whereIn('status', [ApplicantStatus::Lulus->value, ApplicantStatus::TidakLulus->value])
            ->chunkById(200, function ($daftar) use (&$diberitahu): void {
                foreach ($daftar as $pendaftar) {
                    $diberitahu += (int) $this->kirim($pendaftar);
                }
            });

        return $diberitahu;
    }

    /** Sends the outcome of a decision taken after the announcement. */
    public function beritahuBilaDiumumkan(PmbPendaftar $pendaftar): bool
    {
        $pendaftar->loadMissing(['akun', 'gelombang']);

        if (!$pendaftar->gelombang->sudahDiumumkan()) {
            return false;
        }

        return $this->kirim($pendaftar);
    }

    private function kirim(PmbPendaftar $pendaftar): bool
    {
        if ($pendaftar->akun === null) {
            return false;
        }

        return $this->notifier->kirimSekali(
            $pendaftar->akun,
            'pmb.hasil.'.$pendaftar->id.'.'.$pendaftar->status->value,
            new HasilSeleksiDiumumkan($pendaftar),
        );
    }

    /** Applicants in the selection pool still without a decision. */
    public function belumDiputuskan(PmbGelombang $gelombang): int
    {
        return PmbPendaftar::query()
            ->where('pmb_gelombang_id', $gelombang->id)
            ->where('status', ApplicantStatus::Seleksi->value)
            ->count();
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\JenisRiwayat;
use App\Models\Helpdesk\HelpdeskBalasan;
use App\Models\Helpdesk\HelpdeskTiket;
use App\Models\Kemahasiswaan\CutiMahasiswa;
use App\Models\Kemahasiswaan\KegiatanLapanganLogbook;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Pmb\PmbBerkas;
use App\Models\Sdm\PenugasanDosen;
use App\Models\TugasAkhir\Proposal;
use App\Services\Berkas\BerkasService;
use App\Support\Portal;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The only way an uploaded document leaves the server.
 *
 * Files live on a private disk the web server cannot reach, so there is no URL
 * that serves them directly. Every download passes through here, and every
 * route below decides who may see that particular file — not merely who is
 * signed in.
 *
 * The distinction matters: "any authenticated staff member" would let the
 * finance office read applicants' family cards, and any logged-in student read
 * another student's sick note.
 */
class BerkasController extends Controller
{
    public function __construct(private readonly BerkasService $berkas) {}

    /**
     * An applicant's supporting document — admissions staff only, with one
     * exception: the finance office may open a transfer receipt, which it
     * needs to match the payment, and nothing else the applicant uploaded.
     */
    public function pmb(PmbBerkas $berkas): StreamedResponse
    {
        $aktor = Portal::user();

        abort_unless(
            ($aktor?->hasPermissionTo('pmb.view', 'staff') ?? false)
                || ($berkas->jenis === 'bukti_bayar' && ($aktor?->hasPermissionTo('keuangan.view', 'staff') ?? false)),
            403,
        );

        return $this->alirkan($berkas->file_path, $berkas->nama_file);
    }

    /**
     * A leave application's supporting document.
     *
     * Readable by the staff who decide it, and by the student it belongs to —
     * nobody else. A student reading a classmate's medical certificate is the
     * failure this check exists to prevent.
     */
    public function cuti(CutiMahasiswa $cuti): StreamedResponse
    {
        $aktor = Portal::user();

        $boleh = ($aktor?->hasPermissionTo('mahasiswa.view', 'staff') ?? false)
            || ($aktor instanceof Mahasiswa && $aktor->id === $cuti->mahasiswa_id);

        abort_unless($boleh, 403);

        return $this->alirkan(
            $cuti->dokumen_path,
            'dokumen-cuti-'.$cuti->tahunAkademik->kode.'.pdf',
        );
    }

    /**
     * The attachment on a ticket's opening message.
     *
     * Whoever may read the ticket may read its attachment — the policy already
     * encodes "the applicant, their unit's staff, or campus-wide staff", and
     * repeating that here would be a second copy to keep in step.
     */
    public function helpdeskTiket(HelpdeskTiket $tiket): StreamedResponse
    {
        $this->authorize('view', $tiket);

        return $this->alirkan($tiket->lampiran_path, $tiket->lampiran_nama ?? 'lampiran');
    }

    /**
     * The attachment on a reply.
     *
     * One extra rule beyond the ticket's: an attachment on an internal note is
     * staff-only, like the note itself.
     */
    public function helpdeskBalasan(HelpdeskBalasan $balasan): StreamedResponse
    {
        $tiket = $balasan->tiket;

        $this->authorize($balasan->is_internal ? 'handle' : 'view', $tiket);

        return $this->alirkan($balasan->lampiran_path, $balasan->lampiran_nama ?? 'lampiran');
    }

    /**
     * The attachment on a KKN/PKL log entry: its author, the group's field
     * supervisor, and staff — KegiatanLapanganLogbookPolicy.
     */
    public function logbookKegiatan(KegiatanLapanganLogbook $logbook): StreamedResponse
    {
        $this->authorize('view', $logbook);

        return $this->alirkan($logbook->berkas_path, $logbook->berkas_nama ?? 'lampiran');
    }

    /**
     * A final-project proposal version: the student, the prospective or
     * assigned supervisor, and final-project staff — ProposalPolicy.
     */
    public function proposalTugasAkhir(Proposal $proposal): StreamedResponse
    {
        $this->authorize('view', $proposal);

        return $this->alirkan($proposal->berkas_path, $proposal->berkas_nama ?? 'proposal.pdf');
    }

    /**
     * The decree or certificate behind a personnel-history row.
     *
     * Its owner and the personnel office — the same reach as the row itself,
     * so the policy that guards the row guards the file.
     */
    public function riwayatKepegawaian(JenisRiwayat $jenis, Model $riwayat): StreamedResponse
    {
        $this->authorize('view', $riwayat);

        return $this->alirkan(
            $riwayat->dokumen_path,
            $jenis->value.'-'.$riwayat->uuid.'.'.pathinfo((string) $riwayat->dokumen_path, PATHINFO_EXTENSION),
        );
    }

    /**
     * The evidence behind a lecturer's recorded activity — the decree or
     * invitation a staff member opens before verifying it for IKU.
     */
    public function penugasanDosen(PenugasanDosen $penugasan): StreamedResponse
    {
        $this->authorize('view', $penugasan);

        return $this->alirkan(
            $penugasan->dokumen_path,
            'bukti-kegiatan-'.$penugasan->uuid.'.'.pathinfo((string) $penugasan->dokumen_path, PATHINFO_EXTENSION),
        );
    }

    /**
     * Streams the file, or 404s when it is missing.
     *
     * A row pointing at a file that is no longer on disk is a broken link, not
     * a server error — it happens after a restore that skipped storage, and the
     * operator needs to see which document is missing rather than a stack trace.
     */
    private function alirkan(?string $path, string $namaTampil): StreamedResponse
    {
        abort_unless($this->berkas->ada($path), 404, 'Berkas tidak ditemukan di penyimpanan.');

        return response()->streamDownload(
            function () use ($path): void {
                readfile($this->berkas->jalurPenuh($path));
            },
            $namaTampil,

            // nosniff so a browser cannot be talked into executing a document
            // that claims one type and contains another.
            ['X-Content-Type-Options' => 'nosniff'],
        );
    }
}

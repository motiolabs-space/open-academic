<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Kemahasiswaan\CutiMahasiswa;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Pmb\PmbBerkas;
use App\Services\Berkas\BerkasService;
use App\Support\Portal;
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

    /** An applicant's supporting document — admissions staff only. */
    public function pmb(PmbBerkas $berkas): StreamedResponse
    {
        abort_unless(
            Portal::user()?->hasPermissionTo('pmb.view', 'staff') ?? false,
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

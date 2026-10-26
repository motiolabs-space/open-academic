<?php

declare(strict_types=1);

namespace App\Http\Controllers\Pmb;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pmb\UnggahBerkasPendaftarRequest;
use App\Models\Pmb\PmbBerkas;
use App\Models\Pmb\PmbPendaftar;
use App\Services\Berkas\BerkasService;
use App\Services\Pmb\PendaftaranPmbService;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The applicant's own documents.
 *
 * Routes are scope-bound: a document id that belongs to another application
 * is a 404 before it reaches the policy, which then checks the application is
 * this account's.
 */
class BerkasPendaftarController extends Controller
{
    public function __construct(
        private readonly PendaftaranPmbService $pendaftaran,
        private readonly BerkasService $berkas,
    ) {}

    public function store(UnggahBerkasPendaftarRequest $request, PmbPendaftar $pendaftar): RedirectResponse
    {
        $this->pendaftaran->unggahBerkas($pendaftar, (string) $request->input('jenis'), $request->file('berkas'));

        return back()->with('sukses', __('pmb.formulir.berkas_terunggah'));
    }

    public function show(PmbPendaftar $pendaftar, PmbBerkas $berkas): StreamedResponse
    {
        $this->authorize('view', $pendaftar);

        abort_unless($this->berkas->ada($berkas->file_path), 404);

        return response()->streamDownload(
            function () use ($berkas): void {
                readfile($this->berkas->jalurPenuh($berkas->file_path));
            },
            $berkas->nama_file,
            ['X-Content-Type-Options' => 'nosniff'],
        );
    }

    public function destroy(PmbPendaftar $pendaftar, PmbBerkas $berkas): RedirectResponse
    {
        $this->authorize('update', $pendaftar);

        $this->pendaftaran->hapusBerkas($pendaftar, $berkas);

        return back()->with('sukses', __('pmb.formulir.berkas_dihapus'));
    }
}

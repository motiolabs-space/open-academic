<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Surat\VerifikasiSurat;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The public verification page.
 *
 * No authentication, deliberately: the people who need it are a bank clerk, an
 * embassy officer, an employer — none of whom have an account here, and asking
 * them to get one would mean nobody ever verifies anything.
 *
 * Everything protective therefore has to live in what the page discloses and
 * how it can be reached. It shows only enough to compare against the paper in
 * the reader's hand, it is keyed on an unguessable handle, and the manual form
 * demands two facts that are both printed on the document.
 */
class VerifikasiSuratController extends Controller
{
    public function __construct(private readonly VerifikasiSurat $verifikasi) {}

    /** The QR destination. */
    public function tampil(string $uuid): View
    {
        abort_unless((bool) config('surat.verifikasi.aktif'), 404);

        $surat = $this->verifikasi->lewatUuid($uuid);

        return view('verifikasi.surat', [
            'laporan' => $surat !== null ? $this->verifikasi->laporan($surat) : null,
            'dicari' => null,
        ]);
    }

    /** The form, for a document whose QR will not scan. */
    public function formulir(): View
    {
        abort_unless((bool) config('surat.verifikasi.aktif'), 404);

        return view('verifikasi.surat', ['laporan' => null, 'dicari' => null]);
    }

    public function cari(Request $request): View
    {
        abort_unless((bool) config('surat.verifikasi.aktif'), 404);

        $data = $request->validate([
            'nomor' => ['required', 'string', 'max:96'],
            'nim' => ['required', 'string', 'max:32'],
        ]);

        $surat = $this->verifikasi->lewatNomor($data['nomor'], $data['nim']);

        return view('verifikasi.surat', [
            'laporan' => $surat !== null ? $this->verifikasi->laporan($surat) : null,

            // Echoed so the form keeps what was typed, and so a miss can say
            // which number it looked for.
            'dicari' => $data['nomor'],
        ]);
    }
}

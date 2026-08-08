<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mahasiswa;

use App\Http\Controllers\Controller;
use App\Services\Akademik\KrsService;
use App\Services\Dokumen\CetakService;
use App\Support\Portal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The two documents a student prints for themselves.
 *
 * Neither takes an identifier: the student is whoever is signed in. An
 * endpoint that accepted a NIM would need an ownership check, and an ownership
 * check is a thing that can be written wrong — not accepting the parameter at
 * all cannot be.
 */
class CetakController extends Controller
{
    public function __construct(
        private readonly CetakService $cetak,
        private readonly KrsService $krsService,
    ) {}

    public function ktm(): Response
    {
        $mahasiswa = Portal::user();

        return $this->cetak->ktm($mahasiswa)
            ->download('KTM-'.$mahasiswa->nim.'.pdf');
    }

    /**
     * Exam card for the running term.
     *
     * Refusals are redirected back with their reason rather than rendered as an
     * error page: "you still owe Rp 1.200.000" is something the student can act
     * on, and a 403 is not.
     */
    public function kartuUjian(): StreamedResponse|Response|RedirectResponse
    {
        $mahasiswa = Portal::user();
        $krs = $this->krsService->bukaAtauAmbil($mahasiswa, Portal::term());

        $kelayakan = $this->cetak->kelayakanKartuUjian($krs);

        if (!$kelayakan['layak']) {
            return back()->with('galat', 'Kartu ujian belum dapat dicetak. '
                .implode(' ', $kelayakan['alasan']));
        }

        return $this->cetak->kartuUjian($krs)
            ->download('Kartu-Ujian-'.$mahasiswa->nim.'.pdf');
    }
}

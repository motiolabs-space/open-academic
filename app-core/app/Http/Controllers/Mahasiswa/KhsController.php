<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mahasiswa;

use App\Http\Controllers\Controller;
use App\Models\Akademik\Nilai;
use App\Models\Akademik\TahunAkademik;
use App\Services\Akademik\KhsService;
use App\Support\Portal;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Kartu Hasil Studi and the cumulative transcript.
 *
 * Read through KhsService, which the staff student page uses as well, so the
 * student's copy of a term and the registrar's copy cannot disagree.
 */
class KhsController extends Controller
{
    public function __construct(private readonly KhsService $khs) {}

    public function __invoke(): View
    {
        $this->authorize('viewAny', Nilai::class);

        $mahasiswa = Portal::user();
        $riwayat = $this->khs->riwayat($mahasiswa);

        return view('mahasiswa.khs', [
            'judul' => 'KHS & Transkrip',
            'konteks' => $mahasiswa->nim.' · '.$mahasiswa->prodi->namaLengkap(),
            'breadcrumb' => ['Portal Mahasiswa' => route('mahasiswa.dashboard'), 'KHS & Transkrip'],
            'mahasiswa' => $mahasiswa,
            'riwayat' => $riwayat,
            'nilaiPerTerm' => $this->khs->nilaiPerTerm($mahasiswa),
            'terakhir' => $riwayat->where('is_final', true)->last(),
        ]);
    }

    /**
     * One term's KHS as PDF. The owner is whoever is signed in — the route
     * takes a term, never a student.
     */
    public function cetak(TahunAkademik $term): Response
    {
        $this->authorize('viewAny', Nilai::class);

        $mahasiswa = Portal::user();

        return $this->khs->pdf($mahasiswa, $term)->download($this->khs->namaBerkas($mahasiswa, $term));
    }
}

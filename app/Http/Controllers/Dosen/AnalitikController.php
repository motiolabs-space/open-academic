<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dosen;

use App\Http\Controllers\Controller;
use App\Models\Akademik\KelasKuliah;
use App\Models\Akademik\Rps;
use App\Models\Sdm\Dosen;
use App\Services\Akademik\AnalitikService;
use App\Services\Akademik\JurnalService;
use App\Services\Akademik\RpsService;
use App\Support\Portal;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * What one class actually looks like, in three numbers a lecturer can act on.
 *
 * Scoped to classes the signed-in lecturer teaches. There is no shape of request
 * that returns a colleague's cohort — the same stance as the EDOM results screen.
 */
class AnalitikController extends Controller
{
    public function __construct(
        private readonly AnalitikService $analitik,
        private readonly JurnalService $jurnal,
        private readonly RpsService $rps,
    ) {}

    public function index(): View
    {
        $dosen = $this->dosen();
        $term = Portal::term();

        return view('dosen.analitik', [
            'judul' => 'Analitik Kelas',
            'konteks' => $term->nama,
            'breadcrumb' => ['Dasbor' => route('dosen.dashboard'), 'Analitik'],
            'daftar' => $this->kelasDiampu($dosen, $term->id),
        ]);
    }

    public function kelas(KelasKuliah $kelas): View
    {
        $dosen = $this->dosen();

        abort_unless($kelas->dosen->contains('id', $dosen->id), 403);

        $rps = Rps::untuk($kelas->mata_kuliah_id, $kelas->tahun_akademik_id);

        return view('dosen.analitik-kelas', [
            'judul' => 'Analitik '.$kelas->mataKuliah->nama,
            'konteks' => 'Kelas '.$kelas->nama,
            'breadcrumb' => [
                'Dasbor' => route('dosen.dashboard'),
                'Analitik' => route('dosen.analitik'),
                $kelas->mataKuliah->kode,
            ],

            'kelas' => $kelas,
            'kehadiran' => $this->analitik->kehadiran($kelas),
            'penilaian' => $this->analitik->penilaian($kelas),
            'penguasaan' => $this->analitik->penguasaanKelas($kelas),
            'perhatian' => $this->analitik->perluPerhatian($kelas),
            'keterlaksanaan' => $this->jurnal->keterlaksanaan($kelas),

            'rps' => $rps,

            // The gap an accreditation visit looks for: outcomes the plan claims
            // that nothing in this class measures.
            'cplTanpaPengukur' => $rps === null
                ? collect()
                : $this->rps->cplTanpaPengukur($rps, $kelas),
        ]);
    }

    /** @return Collection<int, KelasKuliah> */
    private function kelasDiampu(Dosen $dosen, int $termId)
    {
        return KelasKuliah::query()
            ->with('mataKuliah')
            ->where('tahun_akademik_id', $termId)
            ->whereHas('dosen', fn ($q) => $q->where('dosen.id', $dosen->id))
            ->orderBy('nama')
            ->get();
    }

    private function dosen(): Dosen
    {
        $dosen = Portal::user();

        abort_unless($dosen instanceof Dosen, 403);

        return $dosen;
    }
}

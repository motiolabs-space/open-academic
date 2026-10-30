<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mahasiswa;

use App\Http\Controllers\Controller;
use App\Models\Akademik\CapaianCplMahasiswa;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Services\Akademik\AnalitikService;
use App\Services\Akademik\RekapCapaianService;
use App\Support\Portal;
use Illuminate\View\View;

/**
 * A student's own outcome attainment.
 *
 * The view that justifies the whole module. A mark of 68 in three courses says
 * nothing a student can act on; "consistently weak on CPL-03 wherever it is
 * measured" points at something specific — and is the sentence an academic
 * advisor can open a conversation with.
 *
 * Two readings, kept apart on the screen: the stored attainment from finalised
 * classes (CPL across courses, CPMK course by course — ADR-038), and the
 * running measurement of components mapped straight to a CPL, which is all
 * classes mapped before ADR-038 have.
 *
 * Scoped to the signed-in student with no identifier in the URL.
 */
class PenguasaanController extends Controller
{
    public function __construct(
        private readonly AnalitikService $analitik,
        private readonly RekapCapaianService $rekap,
    ) {}

    public function __invoke(): View
    {
        $mahasiswa = Portal::user();

        abort_unless($mahasiswa instanceof Mahasiswa, 403);

        $this->authorize('viewOwn', CapaianCplMahasiswa::class);

        return view('mahasiswa.penguasaan', [
            'judul' => 'Capaian Pembelajaran',
            'konteks' => $mahasiswa->prodi->nama,
            'breadcrumb' => ['Portal' => route('mahasiswa.dashboard'), 'Capaian Pembelajaran'],
            'capaian' => $this->rekap->mahasiswa($mahasiswa),
            'hasil' => $this->analitik->penguasaanMahasiswa($mahasiswa),
        ]);
    }
}

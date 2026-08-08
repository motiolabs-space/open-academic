<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mahasiswa;

use App\Http\Controllers\Controller;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Services\Akademik\AnalitikService;
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
 * Scoped to the signed-in student with no identifier in the URL.
 */
class PenguasaanController extends Controller
{
    public function __construct(private readonly AnalitikService $analitik) {}

    public function __invoke(): View
    {
        $mahasiswa = Portal::user();

        abort_unless($mahasiswa instanceof Mahasiswa, 403);

        return view('mahasiswa.penguasaan', [
            'judul' => 'Capaian Pembelajaran',
            'konteks' => $mahasiswa->prodi->nama,
            'breadcrumb' => ['Portal' => route('mahasiswa.dashboard'), 'Capaian Pembelajaran'],
            'hasil' => $this->analitik->penguasaanMahasiswa($mahasiswa),
        ]);
    }
}

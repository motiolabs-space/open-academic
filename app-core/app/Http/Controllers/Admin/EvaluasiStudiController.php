<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\KeputusanEvaluasi;
use App\Http\Controllers\Controller;
use App\Models\Akademik\TahunAkademik;
use App\Models\Kemahasiswaan\EvaluasiStudi;
use App\Models\Sdm\Staff;
use App\Services\Kemahasiswaan\EvaluasiStudiService;
use App\Support\Portal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The evaluation queue, and the screen where a person rules on it.
 *
 * Running the sweep and deciding an outcome are separate permissions on
 * purpose: counting is administrative, ending a degree is not.
 */
class EvaluasiStudiController extends Controller
{
    public function __construct(private readonly EvaluasiStudiService $evaluasi) {}

    public function index(Request $request): View
    {
        $this->izin('mahasiswa.view');

        $term = $request->filled('term')
            ? TahunAkademik::byKode((string) $request->string('term'))
            : null;

        return view('admin.evaluasi-studi', [
            'judul' => 'Evaluasi Studi',
            'konteks' => $term?->nama ?? 'Semua semester',
            'breadcrumb' => ['Dasbor' => route('admin.dashboard'), 'Evaluasi Studi'],

            'antrean' => $this->evaluasi->antrean($term),
            'term' => $term,
            'daftarTerm' => TahunAkademik::orderByDesc('kode')->get(),
            'keputusan' => KeputusanEvaluasi::dapatDipilih(),

            /*
             * The thresholds in force, shown beside the queue.
             *
             * An operator ruling on twelve findings needs to see the rule they
             * are applying without leaving the page — otherwise the rule is
             * whatever they remember it to be.
             */
            'aturan' => [
                'tahap' => (array) config('academic.evaluasi.tahap'),
                'masa_studi' => config('academic.evaluasi.masa_studi_maksimum'),
                'peringatan_ips' => config('academic.evaluasi.peringatan_ips'),
            ],
        ]);
    }

    /**
     * Runs the sweep over a closed term.
     *
     * Produces findings only. Nothing here changes a student's status — see
     * EvaluasiStudiService for why that separation is load-bearing.
     */
    public function jalankan(Request $request): RedirectResponse
    {
        $this->izin('mahasiswa.manage');

        $term = TahunAkademik::byKode((string) $request->string('term'));

        if ($term === null) {
            return back()->with('galat', 'Semester tidak ditemukan.');
        }

        $hasil = $this->evaluasi->jalankan($term);

        return back()->with('sukses', sprintf(
            'Evaluasi %s selesai: %d mahasiswa diperiksa, %d temuan, %d dilewati (status sudah berakhir).',
            $term->nama,
            $hasil['diperiksa'],
            $hasil['temuan'],
            $hasil['dilewati'],
        ));
    }

    public function putuskan(Request $request, EvaluasiStudi $evaluasi): RedirectResponse
    {
        $this->izin('mahasiswa.manage');

        $data = $request->validate([
            'keputusan' => ['required', 'string'],

            // Required by the service too. Validated here as well so the person
            // gets it back on the form rather than as a thrown rule.
            'catatan' => ['required', 'string', 'max:1000'],
        ]);

        $keputusan = KeputusanEvaluasi::tryFrom($data['keputusan']);

        if ($keputusan === null || !in_array($keputusan, KeputusanEvaluasi::dapatDipilih(), true)) {
            return back()->with('galat', 'Keputusan tidak dikenal.');
        }

        $this->evaluasi->putuskan($evaluasi, $keputusan, $this->staf(), $data['catatan']);

        return back()->with('sukses', sprintf(
            'Keputusan "%s" dicatat untuk %s.',
            $keputusan->label(),
            $evaluasi->mahasiswa->nama,
        ));
    }

    public function batalkan(Request $request, EvaluasiStudi $evaluasi): RedirectResponse
    {
        $this->izin('mahasiswa.manage');

        $data = $request->validate(['alasan' => ['required', 'string', 'max:1000']]);

        $this->evaluasi->batalkanKeputusan($evaluasi, $this->staf(), $data['alasan']);

        return back()->with('sukses', 'Keputusan dibatalkan dan evaluasi kembali menunggu. '
            .'Status mahasiswa tidak ikut dipulihkan — ubah lewat layar status bila perlu.');
    }

    private function staf(): Staff
    {
        $staf = Portal::user();

        abort_unless($staf instanceof Staff, 403);

        return $staf;
    }

    private function izin(string $permission): void
    {
        abort_unless(Portal::user()?->hasPermissionTo($permission, 'staff') ?? false, 403);
    }
}

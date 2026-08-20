<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\StatusBkd;
use App\Http\Controllers\Controller;
use App\Models\Akademik\TahunAkademik;
use App\Models\Sdm\BkdLaporan;
use App\Models\Sdm\Dosen;
use App\Services\Sdm\BkdService;
use App\Services\Sdm\EksporSdm;
use App\Services\Sdm\EksporSister;
use App\Support\Portal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Running the reporting round: who owes one, who is assessing it, and getting
 * the numbers out of the building.
 *
 * The export buttons are not a stopgap for the missing SISTER client. They are
 * what a faculty office uses on deadline week and what keeps working when an
 * integration is down or a ministry form changes shape — which it does.
 */
class BkdController extends Controller
{
    public function __construct(
        private readonly BkdService $bkd,
        private readonly EksporSdm $ekspor,
        private readonly EksporSister $sister,
    ) {}

    public function index(Request $request): View
    {
        $this->izin('bkd.view');

        $term = $request->filled('semester')
            ? TahunAkademik::where('kode', $request->string('semester'))->firstOrFail()
            : Portal::term();

        $laporan = BkdLaporan::query()
            ->with(['dosen.prodi', 'asesor1', 'asesor2'])
            ->where('tahun_akademik_id', $term->id)
            ->get();

        return view('admin.bkd', [
            'judul' => 'Beban Kerja Dosen (BKD)',
            'konteks' => $term->nama,
            'breadcrumb' => ['Dasbor' => route('admin.dashboard'), 'BKD'],

            'term' => $term,
            'semesterPilihan' => TahunAkademik::orderByDesc('kode')->pluck('nama', 'kode')->all(),

            'laporan' => $laporan->sortBy(fn (BkdLaporan $l): string => $l->dosen->nama)->values(),

            // The number a faculty office actually chases. Everything else on
            // this screen is context for it.
            'belumMelapor' => $this->bkd->belumMelapor($term),

            'perStatus' => collect(StatusBkd::cases())
                ->mapWithKeys(fn (StatusBkd $s): array => [
                    $s->label() => $laporan->where('status', $s)->count(),
                ])
                ->filter(fn (int $n): bool => $n > 0),

            'asesorPilihan' => Dosen::aktif()->orderBy('nama')->pluck('nama', 'id')->all(),
            'bolehKelola' => Portal::user()?->hasPermissionTo('bkd.manage', 'staff') ?? false,
            'batas' => config('bkd.batas'),

            // Includes the groups that produce nothing, with the reason. A
            // catalogue of only what works lets a campus believe its portfolio
            // is complete because everything visible is green.
            'sisterKatalog' => $this->sister->katalog(),
        ]);
    }

    public function tetapkanAsesor(Request $request, BkdLaporan $laporan): RedirectResponse
    {
        $this->izin('bkd.manage');

        $data = $request->validate([
            'asesor_1' => ['required', 'integer', Rule::exists('dosen', 'id')],
            'asesor_2' => ['nullable', 'integer', Rule::exists('dosen', 'id')],
        ]);

        $this->bkd->tetapkanAsesor(
            $laporan,
            Dosen::findOrFail($data['asesor_1']),
            isset($data['asesor_2']) ? Dosen::find($data['asesor_2']) : null,
        );

        return back()->with('sukses', 'Asesor ditetapkan.');
    }

    public function sahkan(BkdLaporan $laporan): RedirectResponse
    {
        $this->izin('bkd.manage');

        $this->bkd->sahkan($laporan, Portal::user());

        return back()->with('sukses', 'Laporan disahkan.');
    }

    public function unduh(BkdLaporan $laporan): Response
    {
        $this->izin('bkd.view');

        return $this->ekspor->lembarBkd($laporan)
            ->download(sprintf('bkd-%s-%s.pdf',
                $laporan->dosen->nidn ?? $laporan->dosen_id,
                $laporan->tahunAkademik->kode,
            ));
    }

    public function eksporRekap(Request $request): Response
    {
        $this->izin('bkd.view');

        return $this->ekspor->rekapBkdCsv($this->term($request));
    }

    public function eksporKegiatan(Request $request): Response
    {
        $this->izin('bkd.view');

        return $this->ekspor->kegiatanCsv($this->term($request));
    }

    /**
     * One SISTER data group as CSV.
     *
     * 404 rather than an empty file for a group this application cannot
     * record: a file with only a header row would be read as "the campus has
     * no data of this kind", which is a different statement.
     */
    public function eksporSister(string $grup): Response
    {
        $this->izin('bkd.view');

        $meta = $this->sister->katalog()[$grup] ?? null;

        abort_if($meta === null || !$meta['tersedia'], 404);

        return $this->sister->csv($grup);
    }

    /**
     * One lecturer's whole record as JSON.
     *
     * The shape an integration script will submit once credentials exist, and
     * downloadable now so the mapping can be written and reviewed against real
     * data rather than against a guess.
     */
    public function eksporPortofolio(Request $request, Dosen $dosen): Response
    {
        $this->izin('bkd.view');

        return response()->json(
            $this->ekspor->portofolioJson($dosen, $this->term($request)),
            200,
            ['Content-Disposition' => 'attachment; filename="portofolio-'.($dosen->nidn ?? $dosen->uuid).'.json"'],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
    }

    private function term(Request $request): TahunAkademik
    {
        return $request->filled('semester')
            ? TahunAkademik::where('kode', $request->string('semester'))->firstOrFail()
            : Portal::term();
    }

    private function izin(string $permission): void
    {
        abort_unless(Portal::user()?->hasPermissionTo($permission, 'staff') ?? false, 403);
    }
}

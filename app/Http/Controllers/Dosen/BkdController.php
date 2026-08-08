<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dosen;

use App\Enums\KesimpulanBkd;
use App\Enums\StatusBkd;
use App\Enums\UnsurBkd;
use App\Http\Controllers\Controller;
use App\Models\Sdm\BkdLaporan;
use App\Models\Sdm\Dosen;
use App\Services\Sdm\BebanKerjaService;
use App\Services\Sdm\BkdService;
use App\Services\Sdm\EksporSdm;
use App\Support\Portal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * The lecturer's own workload sheet.
 *
 * Opens already filled in, which is the whole point of the module: teaching,
 * supervision, examining, and advising are all recorded here as a by-product of
 * running the semester, and retyping them into a ministry form is work nobody
 * should be doing twice.
 */
class BkdController extends Controller
{
    public function __construct(
        private readonly BkdService $bkd,
        private readonly BebanKerjaService $beban,
        private readonly EksporSdm $ekspor,
    ) {}

    public function index(): View
    {
        $dosen = $this->dosen();
        $term = Portal::term();

        $laporan = $this->bkd->laporan($dosen, $term);

        /*
         * A draft reads live; anything submitted reads the snapshot.
         *
         * The two must never be mixed on one screen. Showing freshly computed
         * lines above a stored total is how somebody signs a sheet whose rows
         * and sum disagree.
         */
        if ($laporan->status->beku()) {
            $baris = $laporan->baris->map(fn ($b): array => [
                'unsur' => $b->unsur,
                'kegiatan' => $b->kegiatan,
                'rincian' => $b->rincian,
                'sksRatus' => $b->sks_ratus,
                'otomatis' => (bool) $b->otomatis,
            ]);

            // Read off the report, not recomputed from the lines. The stored
            // figures are what an assessor signed; deriving them again here
            // would let the two drift apart on the same screen.
            $ringkas = [
                UnsurBkd::Pendidikan->value => $laporan->sks_pendidikan,
                UnsurBkd::Penelitian->value => $laporan->sks_penelitian,
                UnsurBkd::Pengabdian->value => $laporan->sks_pengabdian,
                UnsurBkd::Penunjang->value => $laporan->sks_penunjang,
                'total' => $laporan->sks_total,
            ];
        } else {
            // Computed once and reused. The sheet walks classes, supervision,
            // examining, and advising; doing it twice for the rows and again for
            // the totals doubles the cost of the most-visited screen in the
            // module.
            $lembar = $this->bkd->lembarKerja($dosen, $term);

            $baris = $lembar->map(fn ($b): array => [
                'unsur' => $b->unsur,
                'kegiatan' => $b->kegiatan,
                'rincian' => $b->rincian,
                'sksRatus' => $b->sksRatus,
                'otomatis' => $b->otomatis,
            ]);

            $ringkas = $this->beban->ringkas($lembar);
        }

        return view('dosen.bkd', [
            'judul' => 'Beban Kerja Dosen (BKD)',
            'konteks' => $term->nama,
            'breadcrumb' => ['Dasbor' => route('dosen.dashboard'), 'BKD'],

            'laporan' => $laporan,
            'baris' => $baris->groupBy(fn (array $b): string => $b['unsur']->value),
            'ringkas' => $ringkas,
            'peringatan' => $this->beban->pelanggaranBatas($ringkas),
            'batas' => config('bkd.batas'),
            'wajib' => $dosen->wajibBkd(),
        ]);
    }

    public function ajukan(): RedirectResponse
    {
        $laporan = $this->bkd->laporan($this->dosen(), Portal::term());

        $this->bkd->ajukan($laporan);

        return back()->with('sukses',
            'Laporan diajukan. Rinciannya dibekukan pada keadaan hari ini, sehingga '
            .'perubahan kelas atau bimbingan setelah ini tidak akan mengubahnya.');
    }

    public function unduh(BkdLaporan $laporan): Response
    {
        $dosen = $this->dosen();

        // Own report, or one this lecturer is assessing. No other shape.
        abort_unless(
            $laporan->dosen_id === $dosen->id || $laporan->dinilaiOleh($dosen),
            403,
        );

        return $this->ekspor->lembarBkd($laporan)
            ->download(sprintf('bkd-%s-%s.pdf',
                $laporan->dosen->nidn ?? $laporan->dosen_id,
                $laporan->tahunAkademik->kode,
            ));
    }

    /** The assessor's queue. */
    public function penilaian(): View
    {
        $dosen = $this->dosen();

        $daftar = BkdLaporan::query()
            ->with(['dosen.prodi', 'tahunAkademik'])
            ->where(fn ($q) => $q
                ->where('asesor_1_dosen_id', $dosen->id)
                ->orWhere('asesor_2_dosen_id', $dosen->id))
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [StatusBkd::Diajukan->value])
            ->orderByDesc('diajukan_at')
            ->get();

        return view('dosen.bkd-penilaian', [
            'judul' => 'Penilaian BKD',
            'konteks' => $daftar->where('status', StatusBkd::Diajukan)->count().' menunggu penilaian',
            'breadcrumb' => ['Dasbor' => route('dosen.dashboard'), 'Penilaian BKD'],
            'daftar' => $daftar,
            'kesimpulanPilihan' => KesimpulanBkd::options(),
        ]);
    }

    public function nilai(Request $request, BkdLaporan $laporan): RedirectResponse
    {
        $data = $request->validate([
            'kesimpulan' => ['required', Rule::enum(KesimpulanBkd::class)],
            'catatan' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->bkd->nilai(
            $laporan,
            $this->dosen(),
            KesimpulanBkd::from($data['kesimpulan']),
            $data['catatan'] ?? null,
        );

        return back()->with('sukses', 'Penilaian tercatat dan dosen yang bersangkutan diberi tahu.');
    }

    public function kembalikan(Request $request, BkdLaporan $laporan): RedirectResponse
    {
        $data = $request->validate([
            'alasan' => ['required', 'string', 'max:1000'],
        ]);

        $this->bkd->kembalikan($laporan, $this->dosen(), $data['alasan']);

        return back()->with('sukses', 'Laporan dikembalikan untuk diperbaiki.');
    }

    private function dosen(): Dosen
    {
        $dosen = Portal::user();

        abort_unless($dosen instanceof Dosen, 403);

        return $dosen;
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\SasaranKuesioner;
use App\Enums\TipePertanyaan;
use App\Http\Controllers\Controller;
use App\Http\Requests\Kuesioner\KuesionerRequest;
use App\Http\Requests\Kuesioner\PertanyaanRequest;
use App\Models\Akademik\Prodi;
use App\Models\Kuesioner\Kuesioner;
use App\Models\Kuesioner\KuesionerPertanyaan;
use App\Services\Kuesioner\KuesionerService;
use App\Support\Csv;
use App\Support\Portal;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Writing questionnaires and reading their results.
 *
 * EDOM is a separate module with its own screens, deliberately: it is tied to
 * classes and lecturers, and its results feed the Bridge. This is the general
 * form — a satisfaction survey, a facilities poll — and nothing here reaches
 * an EDOM table.
 */
class KuesionerController extends Controller
{
    public function __construct(private readonly KuesionerService $kuesioner) {}

    public function index(): View
    {
        $this->authorize('viewAny', Kuesioner::class);

        $daftar = Kuesioner::query()
            ->with('prodi')
            ->withCount(['pertanyaan', 'partisipasi'])
            ->orderByDesc('id')
            ->paginate(20);

        return view('admin.kuesioner.index', [
            'judul' => __('kuesioner.judul'),
            'konteks' => __('kuesioner.konteks', ['terbit' => Kuesioner::query()->aktif()->count()]),
            'breadcrumb' => ['Dasbor' => route('admin.dashboard'), __('kuesioner.judul')],
            'daftar' => $daftar,
            'bolehKelola' => Portal::user()->can('manage', Kuesioner::class),
            'sasaranPilihan' => SasaranKuesioner::options(),
            'prodiPilihan' => $this->prodiPilihan(),
            'ambangBawaan' => (int) config('kuesioner.min_responden'),
        ]);
    }

    public function store(KuesionerRequest $request): RedirectResponse
    {
        $kuesioner = $this->kuesioner->buat($request->toDto(), Portal::user());

        return redirect()->route('admin.kuesioner.show', $kuesioner)
            ->with('sukses', __('kuesioner.pesan.dibuat'));
    }

    public function show(Kuesioner $kuesioner): View
    {
        $this->authorize('view', $kuesioner);

        $kuesioner->load(['prodi', 'pembuat', 'pertanyaan']);
        $responden = $this->kuesioner->jumlahResponden($kuesioner);

        return view('admin.kuesioner.show', [
            'judul' => $kuesioner->nama,
            'konteks' => $kuesioner->kode.' · '.$kuesioner->status()->label(),
            'breadcrumb' => [
                'Dasbor' => route('admin.dashboard'),
                __('kuesioner.judul') => route('admin.kuesioner.index'),
                $kuesioner->kode,
            ],
            'kuesioner' => $kuesioner,
            'responden' => $responden,
            'hasilTerbuka' => $this->kuesioner->hasilTerbuka($kuesioner, $responden),
            'bolehKelola' => Portal::user()->can('manage', Kuesioner::class),
            'sasaranPilihan' => SasaranKuesioner::options(),
            'tipePilihan' => TipePertanyaan::options(),
            'prodiPilihan' => $this->prodiPilihan(),
        ]);
    }

    public function perbarui(KuesionerRequest $request, Kuesioner $kuesioner): RedirectResponse
    {
        $this->kuesioner->perbarui($kuesioner, $request->toDto());

        return back()->with('sukses', __('kuesioner.pesan.diperbarui'));
    }

    public function tambahPertanyaan(PertanyaanRequest $request, Kuesioner $kuesioner): RedirectResponse
    {
        $this->kuesioner->tambahPertanyaan($kuesioner, $request->toDto());

        return back()->with('sukses', __('kuesioner.pesan.pertanyaan_ditambah'));
    }

    public function hapusPertanyaan(KuesionerPertanyaan $pertanyaan): RedirectResponse
    {
        $this->authorize('manage', Kuesioner::class);

        $this->kuesioner->hapusPertanyaan($pertanyaan);

        return back()->with('sukses', __('kuesioner.pesan.pertanyaan_dihapus'));
    }

    public function terbitkan(Kuesioner $kuesioner): RedirectResponse
    {
        $this->authorize('manage', Kuesioner::class);

        $this->kuesioner->terbitkan($kuesioner);

        return back()->with('sukses', __('kuesioner.pesan.diterbitkan'));
    }

    public function tutup(Kuesioner $kuesioner): RedirectResponse
    {
        $this->authorize('manage', Kuesioner::class);

        $this->kuesioner->tutup($kuesioner);

        return back()->with('sukses', __('kuesioner.pesan.ditutup'));
    }

    public function hasil(Kuesioner $kuesioner): View
    {
        $this->authorize('view', $kuesioner);

        $responden = $this->kuesioner->jumlahResponden($kuesioner);
        $terbuka = $this->kuesioner->hasilTerbuka($kuesioner, $responden);

        return view('admin.kuesioner.hasil', [
            'judul' => __('kuesioner.hasil.judul'),
            'konteks' => $kuesioner->nama,
            'breadcrumb' => [
                'Dasbor' => route('admin.dashboard'),
                __('kuesioner.judul') => route('admin.kuesioner.index'),
                $kuesioner->kode => route('admin.kuesioner.show', $kuesioner),
                __('kuesioner.hasil.judul'),
            ],
            'kuesioner' => $kuesioner,
            'responden' => $responden,
            'terbuka' => $terbuka,

            // Not computed at all while held back: a screen that reads the
            // answers and then hides them is one template edit from showing
            // them.
            'hasil' => $terbuka ? $this->kuesioner->hasil($kuesioner) : [],
            'batasTeks' => (int) config('kuesioner.tampil_teks'),
        ]);
    }

    public function ekspor(Kuesioner $kuesioner): StreamedResponse
    {
        $this->authorize('view', $kuesioner);

        // Built before the stream opens: a held-back result is refused with a
        // message, not with a half-written file.
        $baris = $this->kuesioner->barisEkspor($kuesioner);

        return response()->streamDownload(function () use ($baris): void {
            $keluaran = fopen('php://output', 'wb');
            fwrite($keluaran, "\xEF\xBB\xBF");

            foreach ($baris as $satu) {
                Csv::tulis($keluaran, $satu);
            }

            fclose($keluaran);
        }, 'kuesioner-'.$kuesioner->kode.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array<int, string> */
    private function prodiPilihan(): array
    {
        return Prodi::query()->orderBy('kode')->get(['id', 'kode', 'nama'])
            ->mapWithKeys(fn (Prodi $p): array => [$p->id => $p->kode.' — '.$p->nama])
            ->all();
    }
}

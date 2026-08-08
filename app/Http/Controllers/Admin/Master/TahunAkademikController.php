<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Master;

use App\Enums\SemesterType;
use App\Http\Requests\Master\TahunAkademikRequest;
use App\Models\Akademik\TahunAkademik;
use App\Services\Akademik\TahunAkademikService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The academic calendar.
 *
 * Creating a term is the first thing a fresh installation needs — without one
 * every portal returns 503 by design — and rolling to the next one is the most
 * consequential routine action a registrar performs all year.
 */
class TahunAkademikController extends MasterController
{
    public function __construct(private readonly TahunAkademikService $service) {}

    public function index(): View
    {
        $this->izin('master.view');

        $daftar = TahunAkademik::query()
            ->withCount(['kelasKuliah', 'krs'])
            ->terbaru()
            ->get();

        return view('admin.master.tahun-akademik', $this->halaman(
            'tahun-akademik',
            $daftar->count().' semester tercatat',
            [
                'daftar' => $daftar,
                'semesterPilihan' => SemesterType::cases(),
            ],
        ));
    }

    public function store(TahunAkademikRequest $request): RedirectResponse
    {
        $this->izin('master.manage');

        $term = $this->service->buat($request->validated());

        return back()->with('sukses', "Semester {$term->nama} ({$term->kode}) dibuat.");
    }

    public function update(TahunAkademikRequest $request, TahunAkademik $term): RedirectResponse
    {
        $this->izin('master.manage');

        $this->service->perbarui($term, $request->validated());

        return back()->with('sukses', "Kalender {$term->nama} diperbarui.");
    }

    public function aktifkan(TahunAkademik $term): RedirectResponse
    {
        $this->izin('master.manage');

        $this->service->aktifkan($term);

        return back()->with('sukses', "{$term->nama} kini menjadi semester berjalan.");
    }

    public function kunci(TahunAkademik $term): RedirectResponse
    {
        $this->izin('master.manage');

        $this->service->kunci($term);

        return back()->with('sukses', "{$term->nama} dikunci.");
    }

    public function bukaKunci(Request $request, TahunAkademik $term): RedirectResponse
    {
        $this->izin('master.manage');

        $validated = $request->validate([
            'alasan' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'alasan.required' => 'Membuka kunci semester wajib disertai alasan yang tercatat.',
        ]);

        $this->service->bukaKunci($term, $validated['alasan']);

        return back()->with('sukses', "Kunci {$term->nama} dibuka dan tercatat pada jejak audit.");
    }
}

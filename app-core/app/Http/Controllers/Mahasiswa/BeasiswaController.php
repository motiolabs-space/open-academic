<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mahasiswa;

use App\DTOs\Keuangan\AjuanBeasiswaData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Keuangan\AjukanBeasiswaRequest;
use App\Models\Keuangan\BeasiswaAjuan;
use App\Models\Keuangan\BeasiswaPenerima;
use App\Models\Keuangan\BeasiswaPeriode;
use App\Services\Keuangan\AjuanBeasiswaService;
use App\Support\Portal;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Scholarships from the student's side (ADR-040 §2): open calls, the
 * student's applications and their outcome, and the awards they hold.
 */
class BeasiswaController extends Controller
{
    public function __construct(private readonly AjuanBeasiswaService $ajuan) {}

    public function index(): View
    {
        $mahasiswa = Portal::user();
        $ipk = $mahasiswa->ipk();

        $terbuka = $this->ajuan->periodeTerbuka()->map(fn (BeasiswaPeriode $p): array => [
            'periode' => $p,
            'halangan' => $this->ajuan->halangan($p, $mahasiswa, $ipk),
        ]);

        return view('mahasiswa.beasiswa', [
            'judul' => __('swalayan.beasiswa.judul'),
            'konteks' => __('swalayan.beasiswa.konteks', ['jumlah' => $terbuka->count()]),
            'breadcrumb' => ['Portal Mahasiswa' => route('mahasiswa.dashboard'), __('swalayan.beasiswa.judul')],
            'terbuka' => $terbuka,
            'ajuan' => BeasiswaAjuan::query()
                ->with(['periode.beasiswa', 'periode.tahunAkademik'])
                ->where('mahasiswa_id', $mahasiswa->id)
                ->latest('diajukan_at')
                ->get(),
            'penerima' => BeasiswaPenerima::query()
                ->with(['beasiswa', 'mulai', 'selesai'])
                ->where('mahasiswa_id', $mahasiswa->id)
                ->orderByDesc('id')
                ->get(),
        ]);
    }

    public function ajukan(AjukanBeasiswaRequest $request, BeasiswaPeriode $periode): RedirectResponse
    {
        $this->ajuan->ajukan($periode, Portal::user(), AjuanBeasiswaData::fromRequest($request));

        return back()->with('sukses', __('swalayan.beasiswa.sukses.diajukan', ['nama' => $periode->nama]));
    }
}

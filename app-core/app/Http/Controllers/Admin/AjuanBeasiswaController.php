<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\DTOs\Keuangan\PeriodeBeasiswaData;
use App\Enums\StatusAjuanBeasiswa;
use App\Http\Controllers\Controller;
use App\Http\Requests\Keuangan\BukaPeriodeBeasiswaRequest;
use App\Http\Requests\Keuangan\PutuskanAjuanBeasiswaRequest;
use App\Models\Akademik\TahunAkademik;
use App\Models\Keuangan\Beasiswa;
use App\Models\Keuangan\BeasiswaAjuan;
use App\Models\Keuangan\BeasiswaPeriode;
use App\Services\Keuangan\AjuanBeasiswaService;
use App\Support\Portal;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Calls for scholarship applications and the review of what came in
 * (ADR-040 §2). Granting goes through BeasiswaService, as on the scheme
 * screen; this controller only adds the inbox in front of it.
 */
class AjuanBeasiswaController extends Controller
{
    public function __construct(private readonly AjuanBeasiswaService $ajuan) {}

    public function bukaPeriode(BukaPeriodeBeasiswaRequest $request): RedirectResponse
    {
        $periode = $this->ajuan->bukaPeriode(
            Beasiswa::whereUuid((string) $request->string('beasiswa'))->firstOrFail(),
            PeriodeBeasiswaData::fromRequest($request),
            Portal::user(),
        );

        return redirect()->route('admin.beasiswa.periode', $periode)
            ->with('sukses', __('swalayan.beasiswa.sukses.periode_dibuka', ['nama' => $periode->nama]));
    }

    public function show(BeasiswaPeriode $periode): View
    {
        $this->authorize('viewAny', BeasiswaAjuan::class);

        $periode->load(['beasiswa', 'tahunAkademik', 'pembuat']);

        $daftar = $periode->ajuan()
            ->with(['mahasiswa.prodi', 'pemutus', 'penerima'])
            ->orderByRaw("CASE status WHEN 'diajukan' THEN 0 ELSE 1 END")
            ->orderBy('diajukan_at')
            ->get();

        return view('admin.beasiswa-periode', [
            'judul' => $periode->nama,
            'konteks' => $periode->beasiswa->nama,
            'breadcrumb' => [
                'Dasbor' => route('admin.dashboard'),
                'Beasiswa' => route('admin.beasiswa'),
                $periode->nama,
            ],
            'periode' => $periode,
            'daftar' => $daftar,
            'menunggu' => $daftar->where('status', StatusAjuanBeasiswa::Diajukan)->count(),
            'kuotaTersisa' => $periode->beasiswa->kuotaTersisa(),
            'daftarTerm' => TahunAkademik::terbaru()->where('kode', '>=', $periode->tahunAkademik->kode)->get(['id', 'kode', 'nama']),
            'bolehPutus' => Portal::user()->can('decide', BeasiswaAjuan::class),
        ]);
    }

    public function tutupPeriode(BeasiswaPeriode $periode): RedirectResponse
    {
        $this->authorize('decide', BeasiswaAjuan::class);

        $this->ajuan->tutupPeriode($periode, Portal::user());

        return back()->with('sukses', __('swalayan.beasiswa.sukses.periode_ditutup'));
    }

    public function putuskan(PutuskanAjuanBeasiswaRequest $request, BeasiswaAjuan $ajuan): RedirectResponse
    {
        if ($request->boolean('tetapkan')) {
            $this->ajuan->tetapkan(
                $ajuan,
                Portal::user(),
                $request->filled('tahun_akademik_selesai_id')
                    ? TahunAkademik::find($request->integer('tahun_akademik_selesai_id'))
                    : null,
                $request->filled('nomor_sk') ? trim((string) $request->string('nomor_sk')) : null,
                $request->catatan(),
            );

            return back()->with('sukses', __('swalayan.beasiswa.sukses.ditetapkan', ['nama' => $ajuan->mahasiswa->nama]));
        }

        $this->ajuan->tolak($ajuan, Portal::user(), (string) $request->catatan());

        return back()->with('sukses', __('swalayan.beasiswa.sukses.ditolak'));
    }
}

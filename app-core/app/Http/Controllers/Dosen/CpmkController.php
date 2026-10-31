<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dosen;

use App\Http\Controllers\Concerns\MenyusunCpmk;
use App\Http\Controllers\Controller;
use App\Http\Requests\Akademik\SimpanCpmkRequest;
use App\Models\Akademik\CpmkVersi;
use App\Models\Akademik\MataKuliah;
use App\Services\Akademik\CpmkService;
use App\Support\Portal;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * The lecturer's side of the outcome set, reached from the RPS.
 *
 * The curriculum is the course's own (CpmkService::kurikulumUntuk) — a
 * lecturer writes the outcomes of the course they teach, in the curriculum it
 * is taught under, and has no curriculum picker to get that wrong with.
 */
class CpmkController extends Controller
{
    use MenyusunCpmk;

    public function __construct(private readonly CpmkService $cpmk) {}

    public function edit(MataKuliah $mataKuliah): View
    {
        $this->authorize('create', [CpmkVersi::class, $mataKuliah]);

        $kurikulum = $this->cpmk->kurikulumUntuk($mataKuliah);

        abort_if($kurikulum === null, 404);

        return view('dosen.cpmk', [
            'judul' => __('capaian.editor.judul'),
            'konteks' => $mataKuliah->kode.' · '.$mataKuliah->nama.' · '.$kurikulum->nama,
            'breadcrumb' => [
                'Dasbor' => route('dosen.dashboard'),
                'RPS' => route('dosen.rps'),
                'CPMK',
            ],
        ] + $this->dataEditor($kurikulum, $mataKuliah, [
            'draf' => route('dosen.cpmk.draf', $mataKuliah),
            'simpan' => fn (CpmkVersi $v): string => route('dosen.cpmk.simpan', $v),
            'terbitkan' => fn (CpmkVersi $v): string => route('dosen.cpmk.terbitkan', $v),
        ], true));
    }

    public function draf(MataKuliah $mataKuliah): RedirectResponse
    {
        $this->authorize('create', [CpmkVersi::class, $mataKuliah]);

        $kurikulum = $this->cpmk->kurikulumUntuk($mataKuliah);

        abort_if($kurikulum === null, 404);

        $this->cpmk->draf($kurikulum, $mataKuliah, Portal::user());

        return redirect()->route('dosen.cpmk', $mataKuliah);
    }

    public function simpan(SimpanCpmkRequest $request, CpmkVersi $versi): RedirectResponse
    {
        $this->authorize('update', $versi);

        $this->cpmk->simpan($versi, $request->rumusan());

        return back()->with('sukses', __('capaian.sukses.draf'));
    }

    public function terbitkan(CpmkVersi $versi): RedirectResponse
    {
        $this->authorize('publish', $versi);

        $versi = $this->cpmk->terbitkan($versi, Portal::user());

        return back()->with('sukses', __('capaian.sukses.terbit', ['versi' => $versi->versi]));
    }
}

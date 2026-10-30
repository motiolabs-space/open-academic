<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\MenyusunCpmk;
use App\Http\Controllers\Controller;
use App\Http\Requests\Akademik\SimpanCpmkRequest;
use App\Models\Akademik\CpmkVersi;
use App\Models\Akademik\Kurikulum;
use App\Models\Akademik\MataKuliah;
use App\Models\Akademik\Prodi;
use App\Services\Akademik\CpmkService;
use App\Support\Portal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The programme's side of the outcome set: every course of a curriculum, and
 * the CPMK editor for each.
 */
class CpmkController extends Controller
{
    use MenyusunCpmk;

    public function __construct(private readonly CpmkService $cpmk) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', CpmkVersi::class);

        $daftarProdi = Prodi::orderBy('nama')->get();

        $prodi = $request->filled('prodi')
            ? $daftarProdi->firstWhere('uuid', (string) $request->string('prodi'))
            : $daftarProdi->first();

        $daftarKurikulum = $prodi === null
            ? collect()
            : Kurikulum::where('prodi_id', $prodi->id)->orderByDesc('is_active')->orderByDesc('tahun_mulai')->get();

        $kurikulum = $request->filled('kurikulum')
            ? $daftarKurikulum->firstWhere('uuid', (string) $request->string('kurikulum'))
            : $daftarKurikulum->first();

        return view('admin.cpmk.index', [
            'judul' => __('capaian.kelola.judul'),
            'konteks' => $kurikulum === null
                ? __('capaian.kelola.tanpa_kurikulum')
                : __('capaian.kelola.konteks', ['prodi' => $prodi->nama, 'kurikulum' => $kurikulum->nama]),
            'breadcrumb' => ['Dasbor' => route('admin.dashboard'), __('capaian.kelola.judul')],

            'daftarProdi' => $daftarProdi,
            'prodi' => $prodi,
            'daftarKurikulum' => $daftarKurikulum,
            'kurikulum' => $kurikulum,
            'daftar' => $kurikulum === null ? collect() : $this->cpmk->ringkasKurikulum($kurikulum),
        ]);
    }

    public function edit(Kurikulum $kurikulum, MataKuliah $mataKuliah): View
    {
        $this->authorize('viewAny', CpmkVersi::class);

        return view('admin.cpmk.edit', [
            'judul' => __('capaian.editor.judul'),
            'konteks' => $mataKuliah->kode.' · '.$mataKuliah->nama.' · '.$kurikulum->nama,
            'breadcrumb' => [
                'Dasbor' => route('admin.dashboard'),
                __('capaian.kelola.judul') => route('admin.cpmk', ['prodi' => $kurikulum->prodi?->uuid, 'kurikulum' => $kurikulum->uuid]),
                $mataKuliah->kode,
            ],
        ] + $this->dataEditor($kurikulum, $mataKuliah, [
            'draf' => route('admin.cpmk.draf', [$kurikulum, $mataKuliah]),
            'simpan' => fn (CpmkVersi $v): string => route('admin.cpmk.simpan', $v),
            'terbitkan' => fn (CpmkVersi $v): string => route('admin.cpmk.terbitkan', $v),
        ], Portal::user()->can('create', [CpmkVersi::class, $mataKuliah])));
    }

    public function draf(Kurikulum $kurikulum, MataKuliah $mataKuliah): RedirectResponse
    {
        $this->authorize('create', [CpmkVersi::class, $mataKuliah]);

        $this->cpmk->draf($kurikulum, $mataKuliah, Portal::user());

        return redirect()->route('admin.cpmk.edit', [$kurikulum, $mataKuliah]);
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

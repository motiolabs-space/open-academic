<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Master;

use App\Models\Akademik\Prodi;
use App\Models\Akademik\ProdiCpl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Programme learning outcomes — the substance of every diploma supplement.
 *
 * Without rows here, the central section of every SKPI this campus issues prints
 * as "not recorded by the issuing institution". That is honest, and it is also
 * the section a foreign reader actually reads.
 *
 * Written once per programme rather than per graduate, which is the only way the
 * English half ever gets filled in: as a per-graduate field it becomes somebody's
 * translation job on the morning of a ceremony, and it stops happening.
 */
class CplController extends MasterController
{
    public function index(Request $request): View
    {
        $this->izin('master.view');

        $daftarProdi = Prodi::query()->withCount('cpl')->orderBy('nama')->get();

        $prodi = $request->filled('prodi')
            ? $daftarProdi->firstWhere('id', $request->integer('prodi'))
            : $daftarProdi->first();

        return view('admin.master.cpl', $this->halaman(
            'cpl',
            $prodi !== null
                ? $prodi->nama.' · '.$prodi->cpl_count.' capaian'
                : 'Belum ada program studi',
            [
                'daftarProdi' => $daftarProdi,
                'prodi' => $prodi,
                'kategoriPilihan' => ProdiCpl::KATEGORI,
                'daftar' => $prodi === null ? collect() : ProdiCpl::query()
                    ->where('prodi_id', $prodi->id)
                    ->orderBy('kategori')
                    ->orderBy('urutan')
                    ->orderBy('kode')
                    ->get()
                    ->groupBy('kategori'),
            ],
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->izin('master.manage');

        ProdiCpl::create($this->validasi($request));

        return back()->with('sukses', 'Capaian pembelajaran ditambahkan.');
    }

    public function update(Request $request, ProdiCpl $cpl): RedirectResponse
    {
        $this->izin('master.manage');

        $cpl->update($this->validasi($request, $cpl));

        return back()->with('sukses', "Capaian {$cpl->kode} diperbarui.");
    }

    /**
     * Removes an outcome.
     *
     * Supplements already issued are unaffected: their contents were frozen at
     * issue, so editing this list never rewrites a document somebody is holding.
     * See PerakitKonten.
     */
    public function destroy(ProdiCpl $cpl): RedirectResponse
    {
        $this->izin('master.manage');

        $cpl->delete();

        return back()->with('sukses',
            'Capaian dihapus. SKPI yang sudah terbit tidak berubah — isinya dibekukan saat penerbitan.');
    }

    /** @return array<string, mixed> */
    private function validasi(Request $request, ?ProdiCpl $cpl = null): array
    {
        return $request->validate([
            'prodi_id' => ['required', 'integer', Rule::exists('prodi', 'id')],
            'kode' => [
                'required', 'string', 'max:16',
                Rule::unique('prodi_cpl', 'kode')
                    ->where('prodi_id', $request->integer('prodi_id'))
                    ->ignore($cpl?->id),
            ],
            'kategori' => ['required', Rule::in(array_keys(ProdiCpl::KATEGORI))],
            'deskripsi' => ['required', 'string', 'max:2000'],
            'deskripsi_en' => ['nullable', 'string', 'max:2000'],
            'urutan' => ['nullable', 'integer', 'min:0', 'max:999'],
        ], [
            'kode.unique' => 'Kode capaian itu sudah dipakai pada program studi ini.',
        ]);
    }
}

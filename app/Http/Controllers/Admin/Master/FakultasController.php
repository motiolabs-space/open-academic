<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Master;

use App\Models\Akademik\Fakultas;
use App\Models\Sdm\Dosen;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FakultasController extends MasterController
{
    public function index(): View
    {
        $this->izin('master.view');

        $daftar = Fakultas::query()
            ->with('dekan')
            ->withCount('prodi')
            ->orderBy('kode')
            ->get();

        return view('admin.master.fakultas', $this->halaman(
            'fakultas',
            $daftar->count().' fakultas',
            [
                'daftar' => $daftar,
                // namaLengkap() reads both title columns; selecting only one
                // would render every name without its prefix.
                'daftarDosen' => Dosen::query()->orderBy('nama')
                    ->get(['id', 'nama', 'gelar_depan', 'gelar_belakang']),
            ],
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->izin('master.manage');

        Fakultas::create($this->validasi($request));

        return back()->with('sukses', 'Fakultas ditambahkan.');
    }

    public function update(Request $request, Fakultas $fakultas): RedirectResponse
    {
        $this->izin('master.manage');

        $fakultas->update($this->validasi($request, $fakultas));

        return back()->with('sukses', "Fakultas {$fakultas->nama} diperbarui.");
    }

    public function destroy(Fakultas $fakultas): RedirectResponse
    {
        $this->izin('master.manage');

        // Deleting a faculty that still holds programmes would orphan every
        // student in them. The database refuses it too (restrictOnDelete); this
        // just turns a 500 into a sentence.
        if ($fakultas->prodi()->exists()) {
            return back()->with(
                'galat',
                'Fakultas ini masih menaungi program studi. Pindahkan atau hapus prodinya lebih dulu.',
            );
        }

        $fakultas->delete();

        return back()->with('sukses', 'Fakultas dihapus.');
    }

    /** @return array<string, mixed> */
    private function validasi(Request $request, ?Fakultas $fakultas = null): array
    {
        return $request->validate([
            'kode' => [
                'required', 'string', 'max:16',
                Rule::unique('fakultas', 'kode')->ignore($fakultas?->id)->whereNull('deleted_at'),
            ],
            'nama' => ['required', 'string', 'max:255'],
            'singkatan' => ['nullable', 'string', 'max:16'],
            'dekan_dosen_id' => ['nullable', 'integer', Rule::exists('dosen', 'id')],
        ]);
    }
}

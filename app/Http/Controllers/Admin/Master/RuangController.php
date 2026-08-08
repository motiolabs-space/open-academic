<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Master;

use App\Models\Akademik\Gedung;
use App\Models\Akademik\Ruang;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Buildings and rooms — the physical constraint behind every timetable.
 *
 * Capacity is not decoration here: a class placed in a room smaller than its
 * quota produces a timetable that cannot actually be taught, and nobody finds
 * out until the first week.
 */
class RuangController extends MasterController
{
    public function index(): View
    {
        $this->izin('master.view');

        $gedung = Gedung::query()
            ->with(['ruang' => fn ($q) => $q->orderBy('kode')])
            ->withCount('ruang')
            ->orderBy('kode')
            ->get();

        return view('admin.master.ruang', $this->halaman(
            'ruang',
            $gedung->count().' gedung · '.$gedung->sum('ruang_count').' ruang',
            [
                'daftarGedung' => $gedung,
                'jenisPilihan' => ['kelas' => 'Ruang Kelas', 'laboratorium' => 'Laboratorium', 'aula' => 'Aula'],
            ],
        ));
    }

    public function storeGedung(Request $request): RedirectResponse
    {
        $this->izin('master.manage');

        Gedung::create($request->validate([
            'kode' => ['required', 'string', 'max:16', Rule::unique('gedung', 'kode')->whereNull('deleted_at')],
            'nama' => ['required', 'string', 'max:255'],
            'alamat' => ['nullable', 'string', 'max:255'],
        ]));

        return back()->with('sukses', 'Gedung ditambahkan.');
    }

    public function destroyGedung(Gedung $gedung): RedirectResponse
    {
        $this->izin('master.manage');

        if ($gedung->ruang()->exists()) {
            return back()->with('galat', 'Gedung ini masih memiliki ruang. Hapus ruangnya lebih dulu.');
        }

        $gedung->delete();

        return back()->with('sukses', 'Gedung dihapus.');
    }

    public function storeRuang(Request $request): RedirectResponse
    {
        $this->izin('master.manage');

        Ruang::create($this->validasiRuang($request) + ['is_active' => true]);

        return back()->with('sukses', 'Ruang ditambahkan.');
    }

    public function updateRuang(Request $request, Ruang $ruang): RedirectResponse
    {
        $this->izin('master.manage');

        $ruang->update($this->validasiRuang($request, $ruang));

        return back()->with('sukses', "Ruang {$ruang->kode} diperbarui.");
    }

    public function destroyRuang(Ruang $ruang): RedirectResponse
    {
        $this->izin('master.manage');

        if ($ruang->jadwal()->exists()) {
            return back()->with(
                'galat',
                'Ruang ini sudah dipakai pada jadwal kuliah. Nonaktifkan saja agar tidak dipilih lagi.',
            );
        }

        $ruang->delete();

        return back()->with('sukses', 'Ruang dihapus.');
    }

    /** @return array<string, mixed> */
    private function validasiRuang(Request $request, ?Ruang $ruang = null): array
    {
        return $request->validate([
            'gedung_id' => ['required', 'integer', Rule::exists('gedung', 'id')],
            'kode' => [
                'required', 'string', 'max:32',
                Rule::unique('ruang', 'kode')->ignore($ruang?->id)->whereNull('deleted_at'),
            ],
            'nama' => ['required', 'string', 'max:255'],
            'kapasitas' => ['required', 'integer', 'min:1', 'max:2000'],
            'jenis' => ['required', Rule::in(['kelas', 'laboratorium', 'aula'])],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }
}

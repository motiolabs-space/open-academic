<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\JenisUnitKerja;
use App\Http\Controllers\Controller;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\Staff;
use App\Models\Sdm\UnitKerja;
use App\Services\Sdm\UnitKerjaService;
use App\Support\Portal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The org chart: one screen, one tree, one head count per branch.
 */
class UnitKerjaController extends Controller
{
    public function __construct(private readonly UnitKerjaService $unit) {}

    public function index(): View
    {
        $this->izin('dosen.view');

        $pohon = $this->unit->pohon();

        return view('admin.unit-kerja', [
            'judul' => 'Unit Kerja',
            'konteks' => $pohon->count().' unit',
            'breadcrumb' => ['Dasbor' => route('admin.dashboard'), 'Unit Kerja'],

            'pohon' => $pohon,

            // Rolled up once here rather than per row in the view, where it
            // would be a subtree walk inside a loop.
            'rekap' => $pohon->mapWithKeys(fn (UnitKerja $u): array => [
                $u->id => [
                    'jalur' => $u->jalur($pohon),
                    'total' => $this->unit->jumlahStafTermasukBawahan($u, $pohon),
                ],
            ]),

            'jenisOptions' => JenisUnitKerja::options(),
            'calonStaf' => Staff::orderBy('nama')->get(),
            'calonDosen' => Dosen::orderBy('nama')->get(),

            // Staff still filed under the old free-text column and never
            // matched to a unit — the leftovers the backfill could not place.
            'belumTerpetakan' => Staff::whereNull('unit_kerja_id')->orderBy('nama')->get(),
        ]);
    }

    public function simpan(Request $request): RedirectResponse
    {
        $this->izin('dosen.manage');

        $data = $this->validasi($request, null);

        $this->unit->buat($data);

        return back()->with('sukses', 'Unit kerja ditambahkan.');
    }

    public function perbarui(Request $request, UnitKerja $unit): RedirectResponse
    {
        $this->izin('dosen.manage');

        $data = $this->validasi($request, $unit);

        $this->unit->simpan($unit, $data);

        return back()->with('sukses', 'Unit kerja diperbarui.');
    }

    public function nonaktifkan(UnitKerja $unit): RedirectResponse
    {
        $this->izin('dosen.manage');

        $this->unit->nonaktifkan($unit);

        return back()->with('sukses', sprintf('Unit "%s" dinonaktifkan.', $unit->nama));
    }

    public function pindahkanStaf(Request $request, Staff $staf): RedirectResponse
    {
        $this->izin('dosen.manage');

        $data = $request->validate(['unit_kerja_id' => ['required', 'integer']]);

        $this->unit->pindahkanStaf($staf, UnitKerja::findOrFail($data['unit_kerja_id']));

        return back()->with('sukses', sprintf('%s dipindahkan.', $staf->nama));
    }

    /** @return array<string, mixed> */
    private function validasi(Request $request, ?UnitKerja $unit): array
    {
        $unik = 'unique:unit_kerja,kode'.($unit !== null ? ','.$unit->id : '');

        return $request->validate([
            'kode' => ['required', 'string', 'max:24', $unik],
            'nama' => ['required', 'string', 'max:255'],
            'jenis' => ['required', 'string', 'in:'.implode(',', array_keys(JenisUnitKerja::options()))],
            'parent_id' => ['nullable', 'integer', 'exists:unit_kerja,id'],
            'kepala_staff_id' => ['nullable', 'integer', 'exists:staff,id'],
            'kepala_dosen_id' => ['nullable', 'integer', 'exists:dosen,id'],
            'keterangan' => ['nullable', 'string', 'max:500'],
        ]);
    }

    private function izin(string $permission): void
    {
        abort_unless(Portal::user()?->hasPermissionTo($permission, 'staff') ?? false, 403);
    }
}

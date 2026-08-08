<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Kemahasiswaan\Yudisium;
use App\Services\Kemahasiswaan\YudisiumService;
use App\Support\Portal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Graduation administration.
 *
 * The screen leads with the checklist rather than a decision button: staff
 * confirming a graduation should see what was verified, and a student who is
 * not eligible should have one place that explains which requirement is
 * missing instead of being sent between three offices.
 */
class YudisiumController extends Controller
{
    public function __construct(private readonly YudisiumService $yudisium) {}

    public function index(): View
    {
        $this->authorizeYudisium('wisuda.view');

        $usulan = Yudisium::query()
            ->with(['mahasiswa.prodi', 'tahunAkademik'])
            ->where('status', '!=', 'ditetapkan')
            ->get();

        $syarat = $this->yudisium->periksaSyaratBanyak($usulan->pluck('mahasiswa'));

        $diajukan = $usulan->map(fn (Yudisium $y): array => [
            'yudisium' => $y,
            'syarat' => $syarat[$y->mahasiswa->id],
        ]);

        return view('admin.yudisium', [
            'judul' => 'Yudisium & Wisuda',
            'konteks' => $diajukan->count().' menunggu penetapan',
            'breadcrumb' => ['Dasbor' => route('admin.dashboard'), 'Yudisium & Wisuda'],
            'diajukan' => $diajukan,
            'kandidat' => $this->yudisium->kandidat(),
            'ditetapkan' => Yudisium::with('mahasiswa.prodi')
                ->where('status', 'ditetapkan')
                ->latest('tanggal_lulus')
                ->limit(15)
                ->get(),
        ]);
    }

    public function ajukan(Request $request): RedirectResponse
    {
        $this->authorizeYudisium('wisuda.manage');

        $validated = $request->validate([
            'mahasiswa_uuid' => ['required', 'string'],
            'judul_tugas_akhir' => ['nullable', 'string', 'max:255'],
        ]);

        $mahasiswa = Mahasiswa::whereUuid($validated['mahasiswa_uuid'])->firstOrFail();

        $this->yudisium->ajukan($mahasiswa, Portal::term(), $validated['judul_tugas_akhir'] ?? null);

        return back()->with('sukses', "Yudisium {$mahasiswa->nama} diajukan.");
    }

    public function tetapkan(Request $request, Yudisium $yudisium): RedirectResponse
    {
        $this->authorizeYudisium('wisuda.manage');

        $validated = $request->validate([
            'nomor_sk' => ['nullable', 'string', 'max:64'],
        ]);

        $this->yudisium->tetapkan($yudisium, Portal::user(), $validated['nomor_sk'] ?? null);

        return back()->with('sukses', "Kelulusan {$yudisium->mahasiswa->nama} ditetapkan.");
    }

    public function batalkan(Request $request, Yudisium $yudisium): RedirectResponse
    {
        $this->authorizeYudisium('wisuda.manage');

        $validated = $request->validate([
            'alasan' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'alasan.required' => 'Pembatalan penetapan kelulusan wajib disertai alasan yang tercatat.',
        ]);

        $this->yudisium->batalkan($yudisium, Portal::user(), $validated['alasan']);

        return back()->with('sukses', 'Penetapan kelulusan dibatalkan dan tercatat pada jejak audit.');
    }

    private function authorizeYudisium(string $permission): void
    {
        abort_unless(Portal::user()?->hasPermissionTo($permission, 'staff') ?? false, 403);
    }
}

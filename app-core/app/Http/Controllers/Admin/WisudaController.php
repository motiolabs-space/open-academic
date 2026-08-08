<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Kemahasiswaan\WisudaPeriode;
use App\Models\Kemahasiswaan\WisudaPeserta;
use App\Models\Kemahasiswaan\Yudisium;
use App\Services\Kemahasiswaan\WisudaService;
use App\Support\Portal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Graduation ceremonies.
 *
 * Separate from yudisium on purpose: the academic decision and the event are
 * different things, and a graduate who skips the ceremony is still a graduate.
 */
class WisudaController extends Controller
{
    public function __construct(private readonly WisudaService $wisuda) {}

    public function index(Request $request): View
    {
        $this->izin('wisuda.view');

        $periode = $request->filled('periode')
            ? WisudaPeriode::find($request->integer('periode'))
            : WisudaPeriode::orderByDesc('tanggal')->first();

        return view('admin.wisuda', [
            'judul' => 'Wisuda',
            'konteks' => $periode?->nama ?? 'Belum ada periode wisuda',
            'breadcrumb' => ['Dasbor' => route('admin.dashboard'), 'Wisuda'],

            'daftarPeriode' => WisudaPeriode::withCount('peserta')->orderByDesc('tanggal')->get(),
            'periode' => $periode,

            'peserta' => $periode === null ? collect() : WisudaPeserta::query()
                ->with(['yudisium.mahasiswa.prodi'])
                ->where('wisuda_periode_id', $periode->id)
                ->orderBy('nomor_urut')
                ->get(),

            // Confirmed graduates not yet placed in any ceremony — the queue
            // this screen exists to clear.
            'menunggu' => Yudisium::query()
                ->with('mahasiswa.prodi')
                ->where('status', 'ditetapkan')
                ->whereDoesntHave('pesertaWisuda')
                ->orderBy('tanggal_lulus')
                ->get(),

            'polaIjazah' => (string) config('academic.ijazah.pattern', '{tahun}/{prodi}/{urut}'),
        ]);
    }

    public function storePeriode(Request $request): RedirectResponse
    {
        $this->izin('wisuda.manage');

        WisudaPeriode::create($request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'tanggal' => ['required', 'date'],
            'lokasi' => ['nullable', 'string', 'max:255'],
            'kuota' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ]) + ['is_pendaftaran_dibuka' => true]);

        return back()->with('sukses', 'Periode wisuda dibuat dan pendaftarannya dibuka.');
    }

    public function bukaPendaftaran(WisudaPeriode $periode): RedirectResponse
    {
        $this->izin('wisuda.manage');

        $this->wisuda->bukaPendaftaran($periode);

        return back()->with('sukses', "Pendaftaran {$periode->nama} dibuka.");
    }

    public function tutupPendaftaran(WisudaPeriode $periode): RedirectResponse
    {
        $this->izin('wisuda.manage');

        $this->wisuda->tutupPendaftaran($periode);

        return back()->with('sukses', "Pendaftaran {$periode->nama} ditutup.");
    }

    public function daftarkan(Request $request, WisudaPeriode $periode): RedirectResponse
    {
        $this->izin('wisuda.manage');

        $validated = $request->validate([
            'yudisium_id' => ['required', 'integer', 'exists:yudisium,id'],
        ]);

        $yudisium = Yudisium::findOrFail($validated['yudisium_id']);

        $this->wisuda->daftarkan($periode, $yudisium);

        return back()->with('sukses', "{$yudisium->mahasiswa->nama} didaftarkan sebagai peserta.");
    }

    public function daftarkanMassal(WisudaPeriode $periode): RedirectResponse
    {
        $this->izin('wisuda.manage');

        $hasil = $this->wisuda->daftarkanMassal($periode);

        $pesan = "{$hasil['didaftarkan']} lulusan didaftarkan.";

        if ($hasil['gagal']->isNotEmpty()) {
            return back()->with('peringatan', $pesan.' Tidak dapat didaftarkan: '
                .$hasil['gagal']->take(5)->implode('; ')
                .($hasil['gagal']->count() > 5 ? ', dan lainnya.' : '.'));
        }

        return back()->with('sukses', $pesan);
    }

    public function batalkan(WisudaPeserta $peserta): RedirectResponse
    {
        $this->izin('wisuda.manage');

        $nama = $peserta->yudisium->mahasiswa->nama;
        $this->wisuda->batalkan($peserta);

        return back()->with('sukses', "{$nama} dikeluarkan dari daftar peserta.");
    }

    public function terbitkanIjazah(Request $request, WisudaPeriode $periode): RedirectResponse
    {
        $this->izin('wisuda.manage');

        $validated = $request->validate([
            'pola' => ['required', 'string', 'max:64'],
        ]);

        $hasil = $this->wisuda->terbitkanNomorIjazah($periode, Portal::user(), $validated['pola']);

        return back()->with('sukses', sprintf(
            '%d nomor ijazah diterbitkan. %d peserta sudah punya nomor dan dilewati — '
                .'nomor ijazah tidak pernah diterbitkan ulang.',
            $hasil['diterbitkan'],
            $hasil['dilewati'],
        ));
    }

    private function izin(string $permission): void
    {
        abort_unless(Portal::user()?->hasPermissionTo($permission, 'staff') ?? false, 403);
    }
}

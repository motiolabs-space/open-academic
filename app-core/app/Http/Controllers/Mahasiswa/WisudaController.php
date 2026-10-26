<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mahasiswa;

use App\Http\Controllers\Controller;
use App\Models\Kemahasiswaan\WisudaPeriode;
use App\Models\Kemahasiswaan\WisudaPeserta;
use App\Services\Kemahasiswaan\SyaratWisudaService;
use App\Services\Kemahasiswaan\WisudaService;
use App\Support\Portal;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * A graduate registering themself for a ceremony.
 *
 * The routes take a period, never a person: whose registration it is comes
 * from the session, so there is no identifier to swap for a classmate's.
 */
class WisudaController extends Controller
{
    public function __construct(
        private readonly WisudaService $wisuda,
        private readonly SyaratWisudaService $syarat,
    ) {}

    public function index(): View
    {
        $this->authorize('viewAny', WisudaPeserta::class);

        $mahasiswa = Portal::user();

        $terdaftar = WisudaPeserta::query()
            ->with('periode')
            ->whereHas('yudisium', fn ($q) => $q->where('mahasiswa_id', $mahasiswa->id))
            ->first();

        return view('mahasiswa.wisuda', [
            'judul' => __('wisuda.judul'),
            'konteks' => $terdaftar !== null
                ? __('wisuda.layar.konteks_terdaftar', ['periode' => $terdaftar->periode->nama])
                : __('wisuda.layar.konteks_belum'),
            'breadcrumb' => ['Portal Mahasiswa' => route('mahasiswa.dashboard'), __('wisuda.judul')],
            'syarat' => $this->syarat->periksa($mahasiswa),
            'periode' => $this->wisuda->periodeTerbuka(),
            'terdaftar' => $terdaftar,
        ]);
    }

    public function daftar(WisudaPeriode $periode): RedirectResponse
    {
        $mahasiswa = Portal::user();

        $this->authorize('create', [WisudaPeserta::class, $mahasiswa]);

        $peserta = $this->wisuda->daftarSendiri($periode, $mahasiswa);

        return back()->with('sukses', __('wisuda.layar.sukses_daftar', [
            'periode' => $periode->nama,
            'urut' => $peserta->nomor_urut,
        ]));
    }

    public function batal(WisudaPeriode $periode): RedirectResponse
    {
        $mahasiswa = Portal::user();
        $peserta = $this->wisuda->pesertaDari($periode, $mahasiswa);

        abort_if($peserta === null, 404);

        $this->authorize('delete', $peserta);

        $this->wisuda->batalkanSendiri($periode, $mahasiswa);

        return back()->with('sukses', __('wisuda.layar.sukses_batal', ['periode' => $periode->nama]));
    }
}

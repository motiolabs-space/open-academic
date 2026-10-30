<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\DTOs\Feeder\LaporanKamusEntitas;
use App\Exceptions\FeederException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PeriksaKamusFeederRequest;
use App\Models\Feeder\FeederDictionaryRun;
use App\Models\Sdm\Staff;
use App\Services\Feeder\PemeriksaKamusFeeder;
use App\Support\Portal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The web-service dictionary report: what the campus's Feeder says each act
 * accepts, beside what each mapper sends.
 *
 * The reading runs in the request — one call per act, a few dozen in all —
 * because an operator checking a Feeder upgrade wants the answer now.
 */
class FeederKamusController extends Controller
{
    public function __construct(private readonly PemeriksaKamusFeeder $pemeriksa) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', FeederDictionaryRun::class);

        $run = $request->filled('run')
            ? FeederDictionaryRun::query()->with(['aksi', 'dijalankanOleh'])->where('uuid', (string) $request->query('run'))->first()
            : $this->pemeriksa->terakhir();

        $laporan = $run !== null ? $this->pemeriksa->laporan($run) : collect();

        return view('admin.feeder-kamus', [
            'judul' => __('feeder.kamus.judul'),
            'konteks' => __('feeder.kamus.konteks'),
            'breadcrumb' => [
                'Dasbor' => route('admin.dashboard'),
                'Neo Feeder PDDIKTI' => route('admin.feeder'),
                __('feeder.kamus.judul'),
            ],
            'run' => $run,
            'laporan' => $laporan,
            'selisih' => $laporan->reject(fn (LaporanKamusEntitas $l): bool => $l->cocok())->values(),
            'riwayat' => FeederDictionaryRun::query()->latest('id')->limit(10)->get(),
            'aktif' => (bool) config('feeder.enabled'),
        ]);
    }

    public function periksa(PeriksaKamusFeederRequest $request): RedirectResponse
    {
        try {
            $staf = Portal::user();
            $run = $this->pemeriksa->periksa($staf instanceof Staff ? $staf : null, $request->validated('versi'));
        } catch (FeederException $e) {
            return back()->with('galat', $e->getMessage());
        }

        $selisih = $this->pemeriksa->laporan($run)->reject(fn (LaporanKamusEntitas $l): bool => $l->cocok())->count();

        return redirect()
            ->route('admin.feeder.kamus', ['run' => $run->uuid])
            ->with($selisih === 0 ? 'sukses' : 'peringatan', __('feeder.kamus.pesan.selesai', [
                'aksi' => $run->jumlah_aksi,
                'gagal' => $run->jumlah_gagal,
                'selisih' => $selisih,
            ]));
    }
}

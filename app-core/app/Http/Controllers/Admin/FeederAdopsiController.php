<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\JalankanAdopsiFeederRequest;
use App\Http\Requests\Admin\PutuskanAdopsiFeederRequest;
use App\Models\Akademik\Prodi;
use App\Models\Feeder\FeederAdoption;
use App\Services\Feeder\FeederAdopsi;
use App\Support\Portal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Taking over the students PDDIKTI already holds.
 *
 * One screen per programme and intake: run the match, read the four
 * outcomes, and decide the ambiguous ones by hand. The run is inline like
 * reconciliation — two paged reads and a local query — because an operator
 * migrating a campus works through the intakes one after another and needs
 * each answer before choosing the next.
 */
class FeederAdopsiController extends Controller
{
    public function __construct(private readonly FeederAdopsi $adopsi) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', FeederAdoption::class);

        $prodi = $request->filled('prodi')
            ? Prodi::query()->whereUuid((string) $request->query('prodi'))->first()
            : null;
        $periode = preg_match('/^\d{4}[123]$/', (string) $request->query('periode')) ? (string) $request->query('periode') : null;

        $laporan = $prodi !== null && $periode !== null ? $this->adopsi->laporan($prodi, $periode) : collect();

        return view('admin.feeder-adopsi', [
            'judul' => __('feeder.adopsi.judul'),
            'konteks' => __('feeder.adopsi.konteks'),
            'breadcrumb' => [
                'Dasbor' => route('admin.dashboard'),
                'Neo Feeder PDDIKTI' => route('admin.feeder'),
                __('feeder.adopsi.judul'),
            ],
            'daftarProdi' => Prodi::query()->orderBy('nama')->get(['id', 'uuid', 'nama', 'kode_pddikti']),
            'prodi' => $prodi,
            'periode' => $periode,
            'laporan' => $laporan,
            'jumlah' => $laporan->countBy(fn (FeederAdoption $a): string => $a->status->value),
            'aktif' => (bool) config('feeder.enabled'),
        ]);
    }

    public function jalankan(JalankanAdopsiFeederRequest $request): RedirectResponse
    {
        $this->authorize('create', FeederAdoption::class);

        $prodi = Prodi::query()->whereUuid($request->validated('prodi'))->firstOrFail();
        $periode = $request->validated('periode');

        $hasil = $this->adopsi->adopsi($prodi, $periode, Portal::term());

        return redirect()
            ->route('admin.feeder.adopsi', ['prodi' => $prodi->uuid, 'periode' => $periode])
            ->with($hasil['ambigu'] > 0 ? 'peringatan' : 'sukses', __('feeder.adopsi.pesan.selesai', [
                'prodi' => $hasil['prodi'],
                'periode' => $hasil['periode'],
                'diadopsi' => $hasil['diadopsi'],
                'sudah' => $hasil['sudah_tertaut'],
                'lokal' => $hasil['hanya_lokal'],
                'feeder' => $hasil['hanya_feeder'],
                'ambigu' => $hasil['ambigu'],
            ]));
    }

    public function tautkan(PutuskanAdopsiFeederRequest $request, FeederAdoption $adopsi): RedirectResponse
    {
        $this->authorize('update', $adopsi);

        $hasil = $this->adopsi->tautkan(
            $adopsi,
            (string) $request->validated('id_registrasi_mahasiswa'),
            (string) $request->validated('catatan'),
            Portal::user(),
            Portal::term(),
        );

        return back()->with('sukses', __('feeder.adopsi.pesan.ditautkan', [
            'nim' => $hasil->mahasiswa()->value('nim'),
            'nim_feeder' => $hasil->nim_feeder ?? '—',
        ]));
    }

    public function pisahkan(PutuskanAdopsiFeederRequest $request, FeederAdoption $adopsi): RedirectResponse
    {
        $this->authorize('update', $adopsi);

        $hasil = $this->adopsi->pisahkan($adopsi, (string) $request->validated('catatan'), Portal::user());

        return back()->with('sukses', __('feeder.adopsi.pesan.dipisahkan', [
            'nim' => $hasil->mahasiswa()->value('nim'),
        ]));
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\PrioritasTiket;
use App\Enums\StatusTiket;
use App\Http\Controllers\Controller;
use App\Http\Requests\Helpdesk\BalasanRequest;
use App\Http\Requests\Helpdesk\DisposisiRequest;
use App\Http\Requests\Helpdesk\UbahStatusRequest;
use App\Models\Helpdesk\HelpdeskKategori;
use App\Models\Helpdesk\HelpdeskTiket;
use App\Models\Sdm\Staff;
use App\Models\Sdm\UnitKerja;
use App\Services\Helpdesk\HelpdeskService;
use App\Support\Portal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The staff side: a queue per unit, and one ticket at a time.
 *
 * The queue shows what the signed-in person may actually work on — their own
 * unit's tickets plus anything assigned to them by name, or the whole campus
 * for whoever holds the manage permission. The scoping lives in the service
 * and the policy, not here; this controller only asks.
 */
class HelpdeskController extends Controller
{
    public function __construct(private readonly HelpdeskService $helpdesk) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', HelpdeskTiket::class);

        $staf = $this->staf();
        $semua = $this->helpdesk->bolehSemua($staf);

        $daftar = $this->helpdesk->antreanUntuk($staf)
            ->with(['pemohon', 'kategori', 'unitKerja', 'petugas'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when(!$request->filled('status') && !$request->boolean('semua'), fn ($q) => $q->terbuka())
            ->when($request->filled('unit'), fn ($q) => $q->where('unit_kerja_id', $request->integer('unit')))
            ->when($request->filled('kategori'), fn ($q) => $q->where('helpdesk_kategori_id', $request->integer('kategori')))
            ->when($request->boolean('lewat_sla'), fn ($q) => $q->whereNotNull('lewat_sla_at'))
            ->cari($request->string('cari'), ['nomor', 'judul'])

            // Late first, then urgent, then oldest untouched — the order a
            // supervisor would read the queue in. Portable CASE, no vendor
            // functions.
            ->orderByRaw('CASE WHEN lewat_sla_at IS NULL THEN 1 ELSE 0 END')
            ->orderByRaw(sprintf(
                "CASE prioritas WHEN '%s' THEN 0 WHEN '%s' THEN 1 WHEN '%s' THEN 2 ELSE 3 END",
                PrioritasTiket::Mendesak->value,
                PrioritasTiket::Tinggi->value,
                PrioritasTiket::Normal->value,
            ))
            ->orderBy('balasan_terakhir_at')
            ->paginate(25)
            ->withQueryString();

        $terbuka = $this->helpdesk->antreanUntuk($staf)->terbuka();

        return view('admin.helpdesk.index', [
            'judul' => __('helpdesk.judul'),
            'konteks' => __('helpdesk.konteks_staf', [
                'terbuka' => (clone $terbuka)->count(),
                'lewat' => (clone $terbuka)->whereNotNull('lewat_sla_at')->count(),
            ]),
            'breadcrumb' => ['Dasbor' => route('admin.dashboard'), __('helpdesk.judul')],
            'daftar' => $daftar,
            'semuaUnit' => $semua,
            'unitPilihan' => UnitKerja::query()->aktif()->orderBy('nama')->pluck('nama', 'id')->all(),
            'kategoriPilihan' => HelpdeskKategori::query()->urut()->pluck('nama', 'id')->all(),
            'statusPilihan' => StatusTiket::options(),
            'filter' => $request->only(['status', 'unit', 'kategori', 'cari', 'lewat_sla', 'semua']),
        ]);
    }

    public function show(HelpdeskTiket $tiket): View|RedirectResponse
    {
        $staf = $this->staf();

        // One's own ticket opens on the applicant's screen, where internal
        // notes are filtered out in SQL.
        if ($tiket->milik($staf)) {
            return redirect()->route('admin.saya.helpdesk.show', $tiket);
        }

        $this->authorize('viewInternal', $tiket);

        $tiket->load(['pemohon', 'pencatat', 'kategori', 'unitKerja', 'petugas']);

        return view('admin.helpdesk.show', [
            'judul' => $tiket->nomor,
            'konteks' => $tiket->judul,
            'breadcrumb' => [
                'Dasbor' => route('admin.dashboard'),
                __('helpdesk.judul') => route('admin.helpdesk'),
                $tiket->nomor,
            ],
            'tiket' => $tiket,
            'percakapan' => $tiket->balasan()->with('penulis')->get(),
            'bolehTangani' => $staf->can('handle', $tiket),
            'bolehBalas' => $staf->can('reply', $tiket),
            'statusManual' => $tiket->status->pilihanManual(),
            'prioritasPilihan' => PrioritasTiket::options(),
            'unitPilihan' => UnitKerja::query()->aktif()->orderBy('nama')->pluck('nama', 'id')->all(),

            // Officers grouped under their unit, so the disposition form can
            // narrow the list as the unit changes without a round trip.
            'petugasPerUnit' => Staff::query()
                ->where('is_active', true)
                ->whereNotNull('unit_kerja_id')
                ->orderBy('nama')
                ->get(['id', 'nama', 'unit_kerja_id'])
                ->groupBy('unit_kerja_id')
                ->map(fn ($daftar) => $daftar->pluck('nama', 'id')->all())
                ->all(),
        ]);
    }

    public function disposisi(DisposisiRequest $request, HelpdeskTiket $tiket): RedirectResponse
    {
        $this->authorize('handle', $tiket);

        $this->helpdesk->disposisi($tiket, $this->staf(), $request->toDto());

        return back()->with('sukses', __('helpdesk.pesan.didisposisi', [
            'unit' => $tiket->fresh()->unitKerja?->nama ?? '—',
        ]));
    }

    public function balas(BalasanRequest $request, HelpdeskTiket $tiket): RedirectResponse
    {
        $dto = $request->toDto();

        // An internal note is handling; a public reply is replying. Different
        // abilities, because a unit that may read a ticket is not thereby a
        // unit that may answer for it.
        $this->authorize($dto->internal ? 'handle' : 'reply', $tiket);

        $this->helpdesk->balas($tiket, $this->staf(), $dto);

        return back()->with('sukses', $dto->internal
            ? __('helpdesk.pesan.catatan_disimpan')
            : __('helpdesk.pesan.balasan_terkirim'));
    }

    public function status(UbahStatusRequest $request, HelpdeskTiket $tiket): RedirectResponse
    {
        $this->authorize('handle', $tiket);

        $this->helpdesk->ubahStatus($tiket, $this->staf(), $request->status(), $request->catatan());

        return back()->with('sukses', __('helpdesk.pesan.status_diubah', [
            'status' => $request->status()->label(),
        ]));
    }

    private function staf(): Staff
    {
        $aktor = Portal::user();

        abort_unless($aktor instanceof Staff, 403);

        return $aktor;
    }
}

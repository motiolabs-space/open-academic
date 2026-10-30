<?php

declare(strict_types=1);

namespace App\Http\Controllers\Helpdesk;

use App\Enums\PrioritasTiket;
use App\Enums\StatusTiket;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Helpdesk\BalasanRequest;
use App\Http\Requests\Helpdesk\TiketBaruRequest;
use App\Models\Helpdesk\HelpdeskFaq;
use App\Models\Helpdesk\HelpdeskKategori;
use App\Models\Helpdesk\HelpdeskTiket;
use App\Models\Kemahasiswaan\Mahasiswa;
use App\Models\Sdm\Dosen;
use App\Models\Sdm\Staff;
use App\Services\Helpdesk\HelpdeskService;
use App\Support\Portal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The applicant's side, shared by the student, lecturer and staff portals.
 *
 * One controller rather than three, because the portals differ only in where
 * they sit in the URL and which guard let the person in. The screens are the
 * same screens: file a ticket, see your own, read the replies, answer. Staff
 * reach it at /admin/tiket-saya, apart from the queue they work.
 *
 * Every lookup starts from the signed-in person's own tickets, so a UUID that
 * belongs to somebody else does not resolve at all rather than resolving and
 * then being refused.
 */
class TiketPemohonController extends Controller
{
    public function __construct(private readonly HelpdeskService $helpdesk) {}

    public function index(Request $request): View
    {
        $pemohon = $this->pemohon();

        $this->authorize('viewOwn', HelpdeskTiket::class);

        $daftar = HelpdeskTiket::query()
            ->milik($pemohon)
            ->with(['kategori', 'unitKerja'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByDesc('balasan_terakhir_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('helpdesk.pemohon.index', [
            'judul' => $this->judul(),
            'konteks' => __('helpdesk.konteks_pemohon', [
                'terbuka' => HelpdeskTiket::query()->milik($pemohon)->terbuka()->count(),
            ]),
            'breadcrumb' => ['Portal' => route($this->dasbor()), $this->judul()],
            'prefix' => $this->prefix(),
            'daftar' => $daftar,
            'kategori' => HelpdeskKategori::query()->aktif()->urut()->get(),
            'prioritasPilihan' => PrioritasTiket::options(),
            'statusPilihan' => StatusTiket::options(),
            'filter' => $request->only(['status']),
            'faq' => HelpdeskFaq::query()->terbit()->urut()->limit(5)->get(),
            'bolehAtasNama' => $pemohon instanceof Staff && $pemohon->can('createOnBehalf', HelpdeskTiket::class),
        ]);
    }

    public function store(TiketBaruRequest $request): RedirectResponse
    {
        $aktor = $this->pemohon();
        $atasNama = $request->pemohonAtasNama();

        // Typed in for somebody else: the ticket is theirs, and the officer
        // stays on the form for the next person in line.
        if ($atasNama !== null) {
            $this->authorize('createOnBehalf', HelpdeskTiket::class);

            $tiket = $this->helpdesk->buat($atasNama, $request->toDto(), $aktor);

            return back()->with('sukses', __('helpdesk.pesan.tiket_dicatat', ['nomor' => $tiket->nomor, 'nama' => $atasNama->nama]));
        }

        $tiket = $this->helpdesk->buat($aktor, $request->toDto());

        return redirect()
            ->route($this->prefix().'.helpdesk.show', $tiket)
            ->with('sukses', __('helpdesk.pesan.tiket_dibuat', ['nomor' => $tiket->nomor]));
    }

    public function show(HelpdeskTiket $tiket): View
    {
        $this->authorize('view', $tiket);

        $tiket->load(['kategori', 'unitKerja', 'petugas', 'pencatat']);

        return view('helpdesk.pemohon.show', [
            'judul' => $tiket->nomor,
            'konteks' => $tiket->judul,
            'breadcrumb' => [
                'Portal' => route($this->dasbor()),
                $this->judul() => route($this->prefix().'.helpdesk'),
                $tiket->nomor,
            ],
            'prefix' => $this->prefix(),
            'tiket' => $tiket,

            // Internal notes never reach this screen — filtered in SQL, not in
            // the template, so a template edit cannot leak them.
            'percakapan' => $tiket->balasanPublik()->with('penulis')->get(),
            'bolehBalas' => $this->pemohon()->can('reply', $tiket),
        ]);
    }

    public function balas(BalasanRequest $request, HelpdeskTiket $tiket): RedirectResponse
    {
        $this->authorize('reply', $tiket);

        $this->helpdesk->balas($tiket, $this->pemohon(), $request->toDto());

        return back()->with('sukses', __('helpdesk.pesan.balasan_terkirim'));
    }

    private function pemohon(): Mahasiswa|Dosen|Staff
    {
        $aktor = Portal::user();

        abort_unless($aktor instanceof Mahasiswa || $aktor instanceof Dosen || $aktor instanceof Staff, 403);

        return $aktor;
    }

    /** The route-name prefix of the current portal: "mahasiswa", "dosen", or "admin.saya". */
    private function prefix(): string
    {
        return match (Portal::role()) {
            UserRole::Dosen => 'dosen',
            UserRole::Staff => 'admin.saya',
            default => 'mahasiswa',
        };
    }

    private function dasbor(): string
    {
        return match (Portal::role()) {
            UserRole::Dosen => 'dosen.dashboard',
            UserRole::Staff => 'admin.dashboard',
            default => 'mahasiswa.dashboard',
        };
    }

    /** Staff already have a "Helpdesk" — the queue they work; this is theirs as applicants. */
    private function judul(): string
    {
        return Portal::role() === UserRole::Staff ? __('helpdesk.staf.judul') : __('helpdesk.judul');
    }
}

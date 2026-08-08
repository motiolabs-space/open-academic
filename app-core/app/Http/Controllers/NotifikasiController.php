<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\KategoriNotifikasi;
use App\Services\Notifikasi\Preferensi;
use App\Support\Portal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * One screen for all three portals.
 *
 * Notifications belong to a person, not to a role, so there is nothing
 * portal-specific to separate. Every query starts from the signed-in actor's own
 * relation rather than from an identifier in the URL — which means there is no
 * way to name someone else's notification, rather than a check that could be
 * forgotten on one route.
 */
class NotifikasiController extends Controller
{
    public function __construct(private readonly Preferensi $preferensi) {}

    public function index(Request $request): View
    {
        $aktor = $this->aktor();

        $daftar = $aktor->notifications()
            // A real column, not a JSON path — see DatabaseKategoriChannel.
            ->when($request->filled('kategori'), fn ($q) => $q->where(
                'kategori',
                $request->string('kategori'),
            ))
            ->when($request->boolean('belum'), fn ($q) => $q->whereNull('read_at'))
            ->paginate(20)
            ->withQueryString();

        return view('notifikasi.index', [
            'judul' => 'Notifikasi',
            'konteks' => $aktor->unreadNotifications()->count().' belum dibaca',
            'breadcrumb' => ['Notifikasi'],
            'daftar' => $daftar,
            'kategoriPilihan' => KategoriNotifikasi::options(),
            'filter' => $request->only(['kategori', 'belum']),
        ]);
    }

    public function baca(string $id): RedirectResponse
    {
        // Scoped to the actor's own relation, so an identifier belonging to
        // somebody else simply does not resolve. No separate ownership check to
        // forget.
        $this->aktor()->notifications()->whereKey($id)->firstOrFail()->markAsRead();

        return back();
    }

    public function bacaSemua(): RedirectResponse
    {
        $this->aktor()->unreadNotifications->markAsRead();

        return back()->with('sukses', 'Semua notifikasi ditandai sudah dibaca.');
    }

    public function preferensi(): View
    {
        return view('notifikasi.preferensi', [
            'judul' => 'Preferensi Notifikasi',
            'konteks' => 'Pengaturan pribadi',
            'breadcrumb' => ['Notifikasi' => route('notifikasi'), 'Preferensi'],
            'baris' => $this->preferensi->ringkasan($this->aktor()),
        ]);
    }

    public function simpanPreferensi(Request $request): RedirectResponse
    {
        $aktor = $this->aktor();
        $dikunci = false;

        foreach (KategoriNotifikasi::cases() as $kategori) {
            $aplikasi = $request->boolean("kategori.{$kategori->value}.aplikasi");
            $email = $request->boolean("kategori.{$kategori->value}.email");

            if ($kategori->wajib() && !$aplikasi) {
                $dikunci = true;
            }

            $this->preferensi->simpan($aktor, $kategori, $aplikasi, $email);
        }

        /*
         * The impossible part is declined out loud.
         *
         * A form that silently corrects what somebody submitted teaches them
         * that the screen lies. Saying which switch did not move, and why, is
         * the difference between a rule and a bug.
         */
        return back()->with('sukses', $dikunci
            ? 'Preferensi disimpan. Catatan dalam aplikasi untuk kategori wajib tetap menyala — '
                .'kategori itu memuat keputusan yang berakibat administratif bagi Anda.'
            : 'Preferensi notifikasi disimpan.');
    }

    private function aktor(): Model
    {
        $aktor = Portal::user();

        abort_if($aktor === null, 403);

        return $aktor;
    }
}

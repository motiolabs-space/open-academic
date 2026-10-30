<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sso\KlienSsoRequest;
use App\Models\Sso\KlienOAuth;
use App\Models\Sso\KlienSso;
use App\Services\Sso\KlienSsoService;
use App\Support\Portal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Applications that may sign people in with a campus account.
 *
 * Registered only when SSO_ENABLED, like every other SSO route: a campus that
 * runs no OAuth server has no screen pretending it does.
 */
class KlienSsoController extends Controller
{
    public function __construct(private readonly KlienSsoService $layanan) {}

    public function index(): Response
    {
        $this->authorize('kelola', KlienSso::class);

        $daftar = $this->layanan->ringkasan();

        return response()
            ->view('admin.sso.index', [
                'judul' => __('sso_klien.judul'),
                'konteks' => __('sso_klien.konteks', ['aktif' => $daftar->where('klien.revoked', false)->count()]),
                'breadcrumb' => ['Dasbor' => route('admin.dashboard'), __('sso_klien.judul')],
                'daftar' => $daftar,
                'firstPartyEnv' => (array) config('sso.first_party'),
            ])
            // The page may carry a secret shown once; keep it out of every cache.
            ->header('Cache-Control', 'no-store, private');
    }

    public function store(KlienSsoRequest $request): RedirectResponse
    {
        ['klien' => $klien, 'rahasia' => $rahasia] = $this->layanan->daftarkan($request->toDto(), Portal::user());

        return redirect()->route('admin.sso-klien.index')->with('rahasia_sekali', [
            'judul' => __('sso_klien.rahasia.baru', ['nama' => $klien->name, 'id' => $klien->getKey()]),
            'nilai' => $rahasia,
            'catatan' => __('sso_klien.rahasia.sekali'),
        ]);
    }

    public function rotasi(KlienOAuth $klien): RedirectResponse
    {
        $this->authorize('kelola', KlienSso::class);

        $rahasia = $this->layanan->rotasiRahasia($klien);

        return back()->with('rahasia_sekali', [
            'judul' => __('sso_klien.rahasia.dirotasi', ['nama' => $klien->name, 'id' => $klien->getKey()]),
            'nilai' => $rahasia,
            'catatan' => __('sso_klien.rahasia.sekali_rotasi'),
        ]);
    }

    public function pihakPertama(Request $request, KlienOAuth $klien): RedirectResponse
    {
        $this->authorize('kelola', KlienSso::class);

        $this->layanan->aturPihakPertama($klien, $request->boolean('pihak_pertama'));

        return back()->with('sukses', __('sso_klien.pesan.pihak_pertama_disimpan'));
    }

    public function cabut(KlienOAuth $klien): RedirectResponse
    {
        $this->authorize('kelola', KlienSso::class);

        $jumlah = $this->layanan->cabut($klien);

        return back()->with('sukses', __('sso_klien.pesan.dicabut', ['nama' => $klien->name, 'jumlah' => $jumlah]));
    }
}

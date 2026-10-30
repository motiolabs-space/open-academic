<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Bridge\KonsumenBridgeRequest;
use App\Http\Requests\Bridge\TokenBridgeRequest;
use App\Http\Requests\Bridge\WebhookBridgeRequest;
use App\Models\Bridge\BridgeConsumer;
use App\Services\Bridge\KonsumenBridgeService;
use App\Support\Portal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

/**
 * One Campus Bridge consumer: what it may read, the tokens it holds, and where
 * its webhooks go.
 *
 * Credentials leave this controller once, in a flashed session value read by
 * the next page and gone after it. That page is sent `no-store`, so the one
 * copy is not kept by the browser's back-forward cache or a proxy either.
 */
class KonsumenBridgeController extends Controller
{
    public function __construct(private readonly KonsumenBridgeService $layanan) {}

    public function store(KonsumenBridgeRequest $request): RedirectResponse
    {
        $konsumen = $this->layanan->daftarkan($request->toDto());

        return redirect()->route('admin.bridge.konsumen.show', $konsumen)
            ->with('sukses', __('bridge.pesan.didaftarkan', ['nama' => $konsumen->nama]));
    }

    public function show(BridgeConsumer $konsumen): Response
    {
        $this->authorize('view', $konsumen);

        return response()
            ->view('admin.bridge.konsumen', [
                'judul' => $konsumen->nama,
                'konteks' => $konsumen->slug,
                'breadcrumb' => [
                    'Dasbor' => route('admin.dashboard'),
                    'Campus Bridge' => route('admin.bridge'),
                    $konsumen->nama,
                ],
                'konsumen' => $konsumen,
                'token' => $konsumen->tokens()->latest('id')->get(['id', 'name', 'abilities', 'last_used_at', 'created_at']),
                'riwayat' => $konsumen->logAktivitas()->limit(15)->get(),
                'bolehKelola' => Portal::user()->can('kelolaKredensial', BridgeConsumer::class),
                'scopeTersedia' => config('bridge.scopes'),
                'eventTersedia' => config('bridge.events'),
                'adaRahasia' => filled($konsumen->webhook_secret),
            ])
            ->header('Cache-Control', 'no-store, private');
    }

    public function perbarui(KonsumenBridgeRequest $request, BridgeConsumer $konsumen): RedirectResponse
    {
        $this->layanan->perbarui($konsumen, $request->toDto());

        return back()->with('sukses', __('bridge.pesan.diperbarui'));
    }

    public function terbitkanToken(TokenBridgeRequest $request, BridgeConsumer $konsumen): RedirectResponse
    {
        $token = $this->layanan->terbitkanToken($konsumen, $request->namaToken());

        return back()->with('rahasia_sekali', [
            'judul' => __('bridge.token.baru', ['nama' => $token->accessToken->name]),
            'nilai' => $token->plainTextToken,
            'catatan' => __('bridge.token.sekali'),
        ]);
    }

    public function cabutToken(BridgeConsumer $konsumen, int $token): RedirectResponse
    {
        $this->authorize('kelolaKredensial', BridgeConsumer::class);

        $this->layanan->cabutToken($konsumen, $token);

        return back()->with('sukses', __('bridge.pesan.token_dicabut'));
    }

    public function aturWebhook(WebhookBridgeRequest $request, BridgeConsumer $konsumen): RedirectResponse
    {
        $this->layanan->aturWebhook($konsumen, $request->toDto());

        return back()->with('sukses', __('bridge.pesan.webhook_disimpan'));
    }

    public function rotasiRahasia(BridgeConsumer $konsumen): RedirectResponse
    {
        $this->authorize('kelolaKredensial', BridgeConsumer::class);

        $rahasia = $this->layanan->rotasiRahasiaWebhook($konsumen);

        return back()->with('rahasia_sekali', [
            'judul' => __('bridge.webhook.rahasia_baru'),
            'nilai' => $rahasia,
            'catatan' => __('bridge.webhook.rahasia_sekali'),
        ]);
    }
}

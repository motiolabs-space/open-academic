<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sso;

use App\Http\Controllers\Controller;
use App\Support\Portal;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Laravel\Passport\Passport;

/**
 * The applications a person has authorised, and the way to take it back.
 *
 * The consent screen tells people they can revoke access later. That sentence
 * has to be true, or the consent it obtained was not informed — so this screen
 * exists for the same reason the consent screen does.
 */
class AplikasiTerhubungController extends Controller
{
    public function index(): View
    {
        $token = Passport::tokenModel();

        $aktif = $token::query()
            ->with('client')
            ->where('user_id', Portal::user()?->getAuthIdentifier())
            ->where('revoked', false)
            ->where('expires_at', '>', now())
            ->latest('created_at')
            ->get()

            // One authorisation can leave several live tokens behind as the
            // consumer refreshes. A person thinks in applications, not tokens,
            // so the list is grouped the way they would describe it.
            ->groupBy('client_id')
            ->map(fn ($tokens) => [
                'client' => $tokens->first()->client,
                'sejak' => $tokens->min('created_at'),
                'scopes' => collect($tokens->pluck('scopes')->flatten())->unique()->values(),
                'jumlah_token' => $tokens->count(),
            ])
            ->values();

        return view('sso.aplikasi-terhubung', [
            'judul' => 'Aplikasi Terhubung',
            'konteks' => $aktif->count().' aplikasi punya akses',
            'breadcrumb' => [Portal::role()?->label() => url('/'), 'Aplikasi Terhubung'],
            'aplikasi' => $aktif,
            'daftarScope' => config('sso.scopes'),
        ]);
    }

    public function cabut(string $client): RedirectResponse
    {
        $token = Passport::tokenModel();

        // Scoped to the signed-in person's own tokens. Without the user_id
        // clause this would revoke that client's access for everybody on
        // campus, from a URL anyone could guess.
        $token::query()
            ->where('user_id', Portal::user()?->getAuthIdentifier())
            ->where('client_id', $client)
            ->where('revoked', false)
            ->update(['revoked' => true]);

        return back()->with('sukses', 'Akses aplikasi dicabut. Aplikasi tersebut tidak lagi dapat membaca data Anda.');
    }
}

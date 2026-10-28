<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * A session lasts only as long as its account and its password.
 *
 * `is_active` used to be checked only inside attempt(), at the password step.
 * A staff member dismissed after misuse kept their open session — and their
 * "ingat saya" cookie, which outlives a dismissal by months — with every
 * permission they held. Likewise an administrator's password reset, or the
 * owner's own change, left every other open session working.
 *
 * So each request compares the account against what the session was opened
 * with: still active, and still the same password. The fingerprint is
 * refreshed after the response, which keeps the device that changed the
 * password signed in while every other one is not. Remember cookies are dealt
 * with separately: the remember token is cycled whenever a password changes
 * (see PutarTokenIngat).
 *
 * Laravel's AuthenticateSession does the password half, but only for the
 * default guard, and it assumes that guard is a SessionGuard — the SSO guard
 * is not. Every portal guard is checked here explicitly: a session is never
 * meant to hold two, but if it does, neither may outlive its account.
 */
class PastikanAkunAktif
{
    private const GUARDS = ['staff', 'dosen', 'mahasiswa', 'pendaftar'];

    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->hasSession()) {
            return $next($request);
        }

        foreach (self::GUARDS as $guard) {
            $user = Auth::guard($guard)->user();

            if ($user === null || $this->masihSah($request, $guard, $user)) {
                continue;
            }

            // logout() also cycles remember_token, which kills the cookie on
            // every other device the account was remembered on.
            Auth::guard($guard)->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            abort_if($request->expectsJson(), 401);

            return redirect()
                ->route($guard === 'pendaftar' ? 'pmb.masuk' : 'login')
                ->with('galat', __($user->getAttribute('is_active') === false ? 'auth.nonaktif' : 'auth.sandi_berubah'));
        }

        $response = $next($request);

        // Also right after a sign-in, so the window between the login POST and
        // the first page is covered too.
        foreach (self::GUARDS as $guard) {
            if ($user = Auth::guard($guard)->user()) {
                $request->session()->put($this->kunci($guard), $this->sidik($user));
            }
        }

        return $response;
    }

    private function masihSah(Request $request, string $guard, Authenticatable $user): bool
    {
        if ($user->getAttribute('is_active') === false) {
            return false;
        }

        $tersimpan = $request->session()->get($this->kunci($guard));

        // Bound to the account it was taken from: a session that changed hands
        // through a fresh sign-in is not a password change.
        if (!is_array($tersimpan) || ($tersimpan['id'] ?? null) !== $user->getAuthIdentifier()) {
            return true;
        }

        return hash_equals((string) $tersimpan['sidik'], $this->sidik($user)['sidik']);
    }

    private function kunci(string $guard): string
    {
        return "sidik_sandi_{$guard}";
    }

    /**
     * A digest of the stored hash: the session never holds the hash itself.
     *
     * @return array{id: mixed, sidik: string}
     */
    private function sidik(Authenticatable $user): array
    {
        return [
            'id' => $user->getAuthIdentifier(),
            'sidik' => hash_hmac('sha256', (string) $user->getAuthPassword(), (string) config('app.key')),
        ];
    }
}

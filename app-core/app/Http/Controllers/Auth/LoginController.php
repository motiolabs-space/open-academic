<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Single sign-in surface for all three portals.
 *
 * The person signing in types one identifier — email, NIM, NIDN or NIP — and
 * the controller works out which guard that belongs to. Nobody should have to
 * know what an auth guard is to log in to their own campus.
 */
class LoginController extends Controller
{
    /**
     * Guards are tried in this order. Students are first because they are by
     * far the largest population, so most sign-ins resolve on the first probe.
     *
     * @var array<string, array<int, string>> guard => login columns
     */
    private const IDENTITY_COLUMNS = [
        'mahasiswa' => ['nim', 'email'],
        'dosen' => ['nidn', 'email'],
        'staff' => ['nip', 'email'],
    ];

    public function show(): View|RedirectResponse
    {
        if ($role = $this->activeRole()) {
            return redirect()->route($role->homeRoute());
        }

        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->ensureIsNotRateLimited();

        $identitas = (string) $request->string('identitas');
        $password = (string) $request->string('password');
        $ingat = $request->boolean('ingat');

        foreach (self::IDENTITY_COLUMNS as $guard => $columns) {
            foreach ($columns as $column) {
                $credentials = [
                    $column => $identitas,
                    'password' => $password,

                    // A deactivated account must not be able to sign in even
                    // with a correct password.
                    'is_active' => true,
                ];

                if (!Auth::guard($guard)->attempt($credentials, $ingat)) {
                    continue;
                }

                $request->clearRateLimiter();

                // One person, one portal, per session. A browser holding two
                // authenticated guards at once makes every later "who is this"
                // question ambiguous, and the wrong answer on a student route
                // is a data leak rather than a cosmetic glitch.
                $this->logoutGuardLain($guard);

                $request->session()->regenerate();

                $user = Auth::guard($guard)->user();
                $user->forceFill(['last_login_at' => now()])->saveQuietly();

                return redirect()->intended(route(UserRole::from($guard)->homeRoute()));
            }
        }

        $request->hitRateLimiter();

        throw ValidationException::withMessages([
            'identitas' => trans('auth.failed'),
        ]);
    }

    public function destroy(Request $request): RedirectResponse
    {
        foreach (array_keys(self::IDENTITY_COLUMNS) as $guard) {
            if (Auth::guard($guard)->check()) {
                Auth::guard($guard)->logout();
            }
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function logoutGuardLain(string $kecuali): void
    {
        foreach (array_keys(self::IDENTITY_COLUMNS) as $guard) {
            if ($guard !== $kecuali && Auth::guard($guard)->check()) {
                Auth::guard($guard)->logout();
            }
        }
    }

    private function activeRole(): ?UserRole
    {
        foreach (array_keys(self::IDENTITY_COLUMNS) as $guard) {
            if (Auth::guard($guard)->check()) {
                return UserRole::from($guard);
            }
        }

        return null;
    }
}

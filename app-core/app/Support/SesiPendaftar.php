<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Pmb\PmbAkun;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Signing an applicant in and out of the admission portal.
 *
 * One person, one portal, per session — the rule LoginController keeps for
 * the three campus guards, kept here in the other direction. A browser that
 * was signed in as staff on a shared registration-desk computer must not stay
 * signed in as staff once an applicant signs in on it.
 */
final class SesiPendaftar
{
    public const GUARD = 'pendaftar';

    /** The campus guards an applicant session displaces. */
    private const GUARD_LAIN = ['staff', 'dosen', 'mahasiswa'];

    public static function masuk(Request $request, PmbAkun $akun, bool $ingat = false): void
    {
        foreach (self::GUARD_LAIN as $guard) {
            if (Auth::guard($guard)->check()) {
                Auth::guard($guard)->logout();
            }
        }

        if (!Auth::guard(self::GUARD)->user()?->is($akun)) {
            Auth::guard(self::GUARD)->login($akun, $ingat);
        }

        $request->session()->regenerate();

        $akun->forceFill(['last_login_at' => now()])->saveQuietly();
    }

    public static function keluar(Request $request): void
    {
        Auth::guard(self::GUARD)->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}

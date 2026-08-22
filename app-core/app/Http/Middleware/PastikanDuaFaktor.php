<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Sdm\Staff;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends staff to enrolment when the campus has made two-factor mandatory.
 *
 * Only when `dua_faktor.wajib` is on. Left off by default: flipping it in the
 * shipped config would send every existing installation's whole staff to a
 * setup screen on the day they pull an update, including the ones mid-deadline.
 *
 * Redirects rather than aborts. A 403 on the dashboard tells someone they are
 * locked out; a redirect tells them what to do about it.
 */
class PastikanDuaFaktor
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!config('dua_faktor.wajib')) {
            return $next($request);
        }

        $staff = Auth::guard('staff')->user();

        if (!$staff instanceof Staff || $staff->duaFaktorAktif()) {
            return $next($request);
        }

        /*
         * The enrolment screen itself must stay reachable, or this redirects
         * to a page that redirects to itself.
         *
         * Logout too: someone who cannot finish enrolment — no phone to hand —
         * must still be able to leave rather than be trapped in a loop.
         */
        if ($request->routeIs('dua-faktor.*') || $request->routeIs('logout')) {
            return $next($request);
        }

        return redirect()->route('dua-faktor.kelola')->with('peringatan',
            'Kampus ini mewajibkan verifikasi dua langkah untuk akun staf. '
            .'Pasang sekarang untuk melanjutkan.');
    }
}

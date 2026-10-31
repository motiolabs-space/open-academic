<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Instalasi;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends every web request to the setup wizard until the installation has an
 * identity and a first super-admin (ADR-043).
 *
 * Without it a fresh install opens on a sign-in page nobody can pass — there
 * is no account yet — and on a brand that is still the .env placeholder.
 *
 * On the web group rather than global, for the same reason as
 * PastikanDuaFaktor: routeIs() only answers once routing has run. The API
 * groups are left alone; they authenticate with tokens no fresh install has.
 */
class ArahkanKeSetup
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs('setup.*') || !Instalasi::perluSetup()) {
            return $next($request);
        }

        return redirect()->route('setup.formulir');
    }
}

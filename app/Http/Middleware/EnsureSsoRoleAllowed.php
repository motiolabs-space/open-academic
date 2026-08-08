<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Portal;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps populations a campus has not opened SSO to out of the flow.
 *
 * Some campuses want to let students authorise apps while staff accounts stay
 * closed until the policy is settled. That is a real staging decision, so it is
 * configuration (`sso.allowed_roles`) rather than something to be argued about
 * in code.
 *
 * Enforced here rather than on the consent screen: a warning banner on a page
 * that still has a working "Izinkan" button is decoration, not a restriction.
 */
class EnsureSsoRoleAllowed
{
    public function handle(Request $request, Closure $next): Response
    {
        $role = Portal::role();

        // Token and device endpoints carry no session user; there is nothing to
        // check and refusing them here would break the whole grant.
        if ($role === null) {
            return $next($request);
        }

        $diizinkan = array_map(trim(...), (array) config('sso.allowed_roles'));

        abort_unless(
            in_array($role->value, $diizinkan, true),
            403,
            'Akun '.$role->label().' belum diizinkan memakai SSO pada instalasi ini.',
        );

        return $next($request);
    }
}

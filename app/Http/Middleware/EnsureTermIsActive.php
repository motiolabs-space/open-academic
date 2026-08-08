<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Support\Portal;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuses portal traffic while no academic term is flagged active.
 *
 * Almost every screen behind the portals reads the active term — today's
 * schedule, the KRS window, the invoice for this semester. Without this guard
 * each of them would fail on its own, in its own way, and a registrar staring
 * at a null-reference stack trace has no idea that the real answer is
 * "someone needs to open the semester".
 *
 * Deliberately 503 rather than 500: the installation is fine, it is simply not
 * ready to serve yet.
 */
class EnsureTermIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Portal::term() !== null) {
            return $next($request);
        }

        $peran = Portal::role();

        return response()->view('errors.tanpa-semester', [
            // Staff are the ones who can fix it; students and lecturers need to
            // know it is not their problem to solve.
            'dapatMemperbaiki' => $peran === UserRole::Staff,
            'peran' => $peran,
        ], Response::HTTP_SERVICE_UNAVAILABLE);
    }
}

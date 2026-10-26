<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Akademik\LanggananKalenderService;
use Illuminate\Http\Response;

/**
 * The subscribable iCalendar feed.
 *
 * Unauthenticated, because Google and Outlook fetch it from their own servers
 * with no session. The token in the URL is the whole of the access check; see
 * LanggananKalenderService for what that means for its contents.
 */
class KalenderIcsController extends Controller
{
    public function __invoke(string $token, LanggananKalenderService $langganan): Response
    {
        $ics = $langganan->ics($token);

        // The same answer for unknown and revoked: a revoked link is not
        // evidence that it once existed.
        abort_if($ics === null, 404);

        return response($ics, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="kalender-akademik.ics"',
            'Cache-Control' => 'private, max-age=900',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }
}

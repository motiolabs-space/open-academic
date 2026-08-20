<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Response headers that limit the damage of a mistake elsewhere.
 *
 * None of these fix a vulnerability on their own. They narrow what an injected
 * script or a hostile embedding can reach if one ever does get through, which
 * on a system holding a campus's transcripts and national identity numbers is
 * worth the few bytes per response.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // The transcript PDF is served inline and must stay embeddable by the
        // portal itself, so framing is same-origin rather than denied outright.
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), payment=()');

        // Camera stays available: the student QR attendance scanner needs it.
        if (!$response->headers->has('Content-Security-Policy')) {
            $response->headers->set('Content-Security-Policy', $this->csp());
        }

        return $response;
    }

    /**
     * The policy is deliberately not maximally strict.
     *
     * Alpine evaluates its `x-` expressions with the Function constructor, so a
     * policy without 'unsafe-eval' silently breaks every interactive element in
     * the portal. Removing that allowance means moving to Alpine's CSP build
     * first — worth doing, but not something to discover in production.
     */
    private function csp(): string
    {
        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-eval' 'unsafe-inline'",

            // Kedua huruf di-host sendiri lewat @fontsource sejak 19 Agustus
            // 2026, jadi pengecualian untuk fonts.googleapis.com dan
            // fonts.gstatic.com dicabut — dan tidak ada lagi alamat IP
            // pengunjung yang sampai ke pihak ketiga hanya karena seorang
            // mahasiswa membuka KHS-nya.
            //
            // `unsafe-inline` pada style-src tetap ada dan bukan sisa yang
            // terlupa: bar kuota menyetel lebarnya lewat atribut style, dan
            // layout menyuntik warna per-tenant sebagai <style>:root{…}</style>.
            "style-src 'self' 'unsafe-inline'",
            "font-src 'self' data:",

            "img-src 'self' data:",

            // No third party should be receiving a campus's academic data, and
            // the Bridge is a server-to-server contract, not a browser one.
            "connect-src 'self'",

            "form-action 'self'",
            "frame-ancestors 'self'",
            "base-uri 'self'",
            'object-src none',
        ]);
    }
}

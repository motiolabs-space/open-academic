<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Bridge;

use App\Models\Akademik\TahunAkademik;
use Illuminate\Http\Request;

/**
 * Query conventions shared by every Bridge endpoint.
 */
trait ResolvesBridgeQuery
{
    /**
     * Page size, clamped to the configured ceiling.
     *
     * A consumer asking for 10 000 rows in one page is not malicious, just
     * optimistic; the cap keeps one such request from holding a connection
     * long enough to matter to the students using the portal.
     */
    protected function perPage(Request $request): int
    {
        $diminta = (int) $request->integer('per_page', (int) config('bridge.api.per_page'));

        return max(1, min($diminta, (int) config('bridge.api.max_per_page')));
    }

    /**
     * The term a request refers to, by PDDIKTI code.
     *
     * Falls back to the active term so `?semester=` may be omitted for the
     * common case, and 404s on an unknown code rather than silently returning
     * an empty set that reads like "no data" instead of "wrong code".
     */
    protected function term(Request $request, bool $wajib = false): ?TahunAkademik
    {
        $kode = $request->string('semester')->toString();

        if ($kode === '') {
            $aktif = TahunAkademik::aktif();

            abort_if($wajib && $aktif === null, 404, 'Tidak ada semester aktif; sertakan parameter semester.');

            return $aktif;
        }

        $term = TahunAkademik::byKode($kode);

        abort_if($term === null, 404, "Semester dengan kode {$kode} tidak ditemukan.");

        return $term;
    }
}

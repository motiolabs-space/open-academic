<?php

declare(strict_types=1);

namespace App\Services\Instalasi;

use App\DTOs\Instalasi\HasilLaporan;
use App\Services\Instalasi\Contracts\InstalasiReporterInterface;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Posts the ping as JSON to `instalasi.telemetri.endpoint`.
 *
 * Timeouts are short and nothing is retried here: the job owns retries, and a
 * slow endpoint must not hold a worker for longer than a few seconds.
 */
class HttpInstalasiReporter implements InstalasiReporterInterface
{
    public function kirim(array $muatan): HasilLaporan
    {
        try {
            $response = Http::timeout((int) config('instalasi.telemetri.timeout'))
                ->connectTimeout((int) config('instalasi.telemetri.connect_timeout'))
                ->acceptJson()
                ->withUserAgent('OpenAcademic/'.config('instalasi.versi'))
                ->post((string) config('instalasi.telemetri.endpoint'), $muatan);
        } catch (Throwable $e) {
            return HasilLaporan::gagal($e->getMessage());
        }

        return $response->successful()
            ? HasilLaporan::berhasil($response->status())
            : HasilLaporan::gagal('HTTP '.$response->status(), $response->status());
    }
}

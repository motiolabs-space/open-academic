<?php

declare(strict_types=1);

namespace App\Services\Instalasi\Contracts;

use App\DTOs\Instalasi\HasilLaporan;

/**
 * Sends an installation ping to Humanix (ADR-043).
 *
 * Implementations never throw: a ping nobody receives is a failed ping, not a
 * failed request, and nothing on the campus side may depend on it arriving.
 */
interface InstalasiReporterInterface
{
    /** @param array<string, string> $muatan the exact body to send */
    public function kirim(array $muatan): HasilLaporan;
}

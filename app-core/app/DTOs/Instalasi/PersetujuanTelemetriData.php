<?php

declare(strict_types=1);

namespace App\DTOs\Instalasi;

use Illuminate\Http\Request;

/** A campus's answer to "may we tell Humanix this installation exists?". */
final readonly class PersetujuanTelemetriData
{
    public function __construct(
        public bool $setuju,
        public bool $sertakanUrl = false,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $setuju = $request->boolean('telemetri_setuju');

        // The URL is an addition to the ping, so it cannot outlive consent to
        // the ping itself.
        return new self($setuju, $setuju && $request->boolean('telemetri_sertakan_url'));
    }
}

<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\StatusPingInstalasi;
use App\Models\System\InstallationPing;
use App\Services\Instalasi\Contracts\InstalasiReporterInterface;
use App\Services\Instalasi\TelemetriService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Sends one installation ping, and reschedules itself on failure (ADR-043).
 *
 * Consent is checked again here, not only when the ping was queued: a campus
 * that withdraws it while the ping waits in the queue must not have it sent
 * anyway. The payload is rebuilt at send time for the same reason, and stored
 * on the row so the campus can read back exactly what left.
 *
 * Retries are scheduled by the job itself, with config('instalasi.telemetri.jeda')
 * between them — except on the sync queue, where a "delayed" retry would run
 * at once and make whoever is waiting on the request sit through every timeout.
 */
class KirimPingInstalasiJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 30;

    public function __construct(public readonly int $pingId) {}

    public function handle(TelemetriService $telemetri, InstalasiReporterInterface $reporter): void
    {
        $ping = InstallationPing::find($this->pingId);

        if ($ping === null || $ping->status === StatusPingInstalasi::Sent) {
            return;
        }

        if (!$telemetri->aktif()) {
            $ping->update(['status' => StatusPingInstalasi::Cancelled]);

            return;
        }

        $muatan = $telemetri->muatan($ping->event);
        $hasil = $reporter->kirim($muatan);
        $percobaan = $ping->attempts + 1;

        $ping->update([
            'payload' => $muatan,
            'attempts' => $percobaan,
            'http_status' => $hasil->statusHttp,
            'error' => $hasil->galat,
            'status' => $hasil->berhasil ? StatusPingInstalasi::Sent : StatusPingInstalasi::Failed,
            'sent_at' => $hasil->berhasil ? now() : null,
        ]);

        if ($hasil->berhasil || $percobaan >= (int) config('instalasi.telemetri.percobaan')) {
            return;
        }

        if (config('queue.default') === 'sync') {
            return;
        }

        $jeda = (array) config('instalasi.telemetri.jeda');

        self::dispatch($ping->id)->delay(now()->addSeconds((int) ($jeda[$percobaan - 1] ?? end($jeda) ?: 600)));
    }
}

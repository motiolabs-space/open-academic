<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\System\LogAktivitas;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Writes one audit-trail row. Queued so an audited save never slows a request.
 *
 * The payload is already flat scalars by the time it reaches here — no model
 * is serialised, so the row stays writable even if the subject is later
 * deleted.
 */
class RecordActivityLogJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /** @param array<string, mixed> $payload */
    public function __construct(private readonly array $payload)
    {
        $this->onQueue('audit');
    }

    public function handle(): void
    {
        LogAktivitas::create($this->payload);
    }
}

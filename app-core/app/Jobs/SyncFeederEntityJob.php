<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Exceptions\FeederException;
use App\Models\Akademik\TahunAkademik;
use App\Services\Feeder\FeederSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Pushes one entity for one term in the background.
 *
 * A reporting run over a full campus takes minutes, not seconds, so it never
 * belongs in a request. The job is safe to re-dispatch: the ledger's hash check
 * makes a repeat run a series of skips rather than a series of duplicates.
 */
class SyncFeederEntityJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(
        public readonly string $entity,
        public readonly int $tahunAkademikId,
        public readonly bool $lewatiValidasi = false,
    ) {
        $this->onQueue('feeder');
    }

    public function handle(FeederSyncService $sync): void
    {
        $term = TahunAkademik::find($this->tahunAkademikId);

        if ($term === null) {
            return;
        }

        try {
            $hasil = $sync->sinkronkan($this->entity, $term, $this->lewatiValidasi);

            Log::info('Sinkronisasi Feeder selesai', $hasil);
        } catch (FeederException $e) {
            // Already recorded per row in the ledger; this is the run-level
            // reason an operator reads on the monitor screen.
            Log::error('Sinkronisasi Feeder dibatalkan', [
                'entity' => $this->entity,
                'term' => $term->kode,
                'pesan' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /** Retrying a whole run automatically would hide a data problem. */
    public function retryUntil(): \DateTimeInterface
    {
        return now()->addMinutes(35);
    }
}

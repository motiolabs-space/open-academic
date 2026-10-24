<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Helpdesk\HelpdeskTiket;
use App\Services\Helpdesk\HelpdeskService;
use Illuminate\Console\Command;

/**
 * Marks tickets that have passed their response deadline.
 *
 * Marks only. It never closes, reassigns, or escalates — a deadline missed is
 * a fact for the office to see at the top of its queue, not a decision the
 * scheduler gets to make on the office's behalf. Idempotent: a ticket is
 * stamped once, and running the command every hour stamps nothing twice.
 */
class HelpdeskSlaCommand extends Command
{
    protected $signature = 'openacademic:helpdesk-sla
                            {--kering : Hitung tanpa menandai apa pun}';

    protected $description = 'Menandai tiket helpdesk yang melewati tenggat SLA (tanpa menutupnya).';

    public function __construct(private readonly HelpdeskService $helpdesk)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        if ($this->option('kering')) {
            $jumlah = HelpdeskTiket::query()
                ->menungguKampus()
                ->whereNull('lewat_sla_at')
                ->whereNotNull('tenggat_sla_at')
                ->where('tenggat_sla_at', '<', now())
                ->count();

            $this->warn("Mode kering: {$jumlah} tiket akan ditandai lewat SLA; tidak ada yang diubah.");

            return self::SUCCESS;
        }

        $jumlah = $this->helpdesk->tandaiLewatSla();

        $this->info("{$jumlah} tiket ditandai lewat SLA.");

        return self::SUCCESS;
    }
}

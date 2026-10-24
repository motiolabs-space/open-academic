<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\DTOs\Keuangan\AturanDenda;
use App\Services\Keuangan\DendaService;
use App\Support\Format;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Charges late fees on overdue invoices. Meant to run once a day, before the
 * due-date reminder so the reminder quotes today's figure.
 *
 * Idempotent by construction — see DendaService — so a second run on the same
 * day, or a run after a week of outage, charges exactly the periods that were
 * owed and nothing twice.
 */
class HitungDendaCommand extends Command
{
    protected $signature = 'openacademic:hitung-denda
                            {--kering : Hitung tanpa menulis apa pun}
                            {--hari= : Hitung seolah hari ini adalah tanggal ini (YYYY-MM-DD)}';

    protected $description = 'Mengenakan denda keterlambatan pada tagihan yang melewati jatuh tempo.';

    public function __construct(private readonly DendaService $denda)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $aturan = AturanDenda::dariConfig();

        if (!$aturan->aktif) {
            $this->warn('Denda keterlambatan nonaktif (PAYMENT_DENDA_AKTIF=false). Tidak ada yang dihitung.');

            return self::SUCCESS;
        }

        $kering = (bool) $this->option('kering');
        $hari = $this->option('hari') ? Carbon::parse((string) $this->option('hari')) : Carbon::today();

        if ($kering) {
            $this->warn('Mode kering: tidak ada denda yang ditulis.');
        }

        $this->line('Aturan: '.$aturan->deskripsi());

        $hasil = $this->denda->jalankan($aturan, $hari, $kering);

        $this->table(
            ['Diperiksa', 'Dikenai denda', 'Total denda hari ini'],
            [[$hasil['diperiksa'], $hasil['dikenai'], Format::rupiah($hasil['total_rupiah'])]],
        );

        return self::SUCCESS;
    }
}

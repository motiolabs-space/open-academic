<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Sdm\AngkaKreditService;
use Illuminate\Console\Command;

/**
 * Rebuilds the derived lines of the credit ledger from BKD and the portfolio.
 *
 * Endorsing a BKD report and saving a degree already credit as they happen.
 * This is for the cases that bypass both — an import, a seeder, a change to
 * config/angka_kredit.php — and is safe to run any number of times: derived
 * lines are updated in place, office-entered lines are never touched.
 */
class SusunAngkaKreditCommand extends Command
{
    protected $signature = 'openacademic:susun-angka-kredit';

    protected $description = 'Menyusun ulang baris turunan buku besar angka kredit dari BKD disahkan dan ijazah.';

    public function __construct(private readonly AngkaKreditService $angkaKredit)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $jumlah = $this->angkaKredit->susunSemua();

        $this->info(__('angka_kredit.pesan.disusun_semua', ['jumlah' => $jumlah]));

        return self::SUCCESS;
    }
}

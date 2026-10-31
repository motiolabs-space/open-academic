<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Instalasi;
use Illuminate\Console\Command;

/**
 * Reopens the setup wizard (ADR-043).
 *
 * The only way back to /setup once it has run, and deliberately a command:
 * whoever can run artisan already controls the server, while a web button
 * would let anyone holding an admin session hand the wizard — and the
 * super-admin it creates — to the next visitor.
 */
class SetupUlangCommand extends Command
{
    protected $signature = 'openacademic:setup-ulang
        {--paksa : Jangan bertanya konfirmasi}';

    protected $description = 'Membuka kembali wizard setup awal di /setup (membuat super admin baru)';

    public function handle(): int
    {
        if (Instalasi::perluSetup()) {
            $this->info('Wizard setup sudah terbuka di /setup.');

            return self::SUCCESS;
        }

        $this->warn('Selama wizard terbuka, SIAPA PUN yang dapat membuka /setup dapat membuat akun super admin.');
        $this->line('Data yang sudah ada tidak dihapus; identitas institusi akan ditimpa isian wizard.');

        if (!$this->option('paksa') && !$this->confirm('Buka kembali wizard setup?', false)) {
            $this->line('Dibatalkan. Tidak ada yang diubah.');

            return self::SUCCESS;
        }

        Instalasi::bukaUlang();
        Instalasi::catat('installation_setup_reopened', __('instalasi.log.setup_dibuka_ulang'));

        $this->info('Wizard setup terbuka di '.url('/setup').'. Selesaikan sesegera mungkin.');

        return self::SUCCESS;
    }
}

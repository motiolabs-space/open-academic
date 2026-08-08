<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Demo;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Removes the demo campus, leaving an empty but usable install.
 *
 * **Removal cannot be surgical, and pretending otherwise would be worse than
 * not offering it.** The demo seeder writes across ~90 tables, and the campus it
 * creates is the database: deleting "just the demo rows" would leave grades
 * without enrolments, invoices without students, and journal entries pointing at
 * classes that no longer exist. So this rebuilds the schema empty instead.
 *
 * What keeps that safe is the marker. This refuses to run unless the database
 * says it was seeded with demo data, so the command can only ever destroy
 * something the application put there itself.
 */
class DemoHapusCommand extends Command
{
    protected $signature = 'openacademic:demo-hapus
        {--paksa : Jangan bertanya konfirmasi}';

    protected $description = 'Menghapus kampus demo dan mengembalikan basis data ke keadaan kosong siap pakai';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('Perintah ini tidak dapat dijalankan pada APP_ENV=production.');

            return self::FAILURE;
        }

        if (!Demo::terpasang()) {
            /*
             * The guard that makes this command safe to have at all.
             *
             * No marker means nothing here was installed by the demo seeder, so
             * whatever is in this database belongs to somebody. Wiping it would
             * be destroying data this application never created.
             */
            $this->error('Basis data ini tidak ditandai sebagai pasangan demo.');
            $this->line('Perintah ini hanya menghapus basis data yang memang diisi oleh '
                .'openacademic:demo-pasang, supaya tidak pernah bisa menghapus kampus sungguhan.');
            $this->newLine();
            $this->line('Untuk mengosongkan basis data ini secara sengaja: php artisan migrate:fresh');

            return self::FAILURE;
        }

        $this->warn('Menghapus SELURUH isi basis data "'.DB::connection()->getDatabaseName().'".');
        $this->line('Data demo dipasang pada: '.Demo::dipasangPada());

        if (!$this->option('paksa') && !$this->confirm('Lanjutkan?', false)) {
            $this->line('Dibatalkan. Tidak ada yang diubah.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->call('migrate:fresh', ['--force' => true]);

        // Left usable rather than merely empty: without roles and permissions
        // nobody can be given access, and the first thing anyone does after
        // clearing the demo is create a real account.
        $this->call('db:seed', ['--class' => RolePermissionSeeder::class, '--force' => true]);

        $this->newLine();
        $this->info('Data demo dihapus. Basis data kosong, skema dan daftar peran siap pakai.');
        $this->line('Pasang lagi dengan: php artisan openacademic:demo-pasang');

        return self::SUCCESS;
    }
}

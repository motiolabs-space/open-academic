<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Demo;
use Database\Seeders\DemoCampusSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Installs the demo campus.
 *
 * This **drops every table and rebuilds them**. It has to: the demo is a whole
 * campus, not a set of rows added to one — three terms of grades, money,
 * admissions and integration state that only make sense together. Adding it on
 * top of existing data would collide on every unique key it owns.
 *
 * Which makes the guards the important part of this command, not the seeding.
 */
class DemoPasangCommand extends Command
{
    protected $signature = 'openacademic:demo-pasang
        {--paksa : Lanjutkan walau basis data sudah berisi (isinya tetap dihapus)}';

    protected $description = 'Memasang kampus demo — MENGHAPUS seluruh isi basis data lebih dulu';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('Perintah ini tidak dapat dijalankan pada APP_ENV=production.');
            $this->line('Data demo memakai kata sandi yang diketahui umum.');

            return self::FAILURE;
        }

        $this->warn('Perintah ini menghapus SELURUH isi basis data '
            .'"'.DB::connection()->getDatabaseName().'", lalu mengisinya dengan data demo.');

        $isi = $this->cacahIsi();

        if ($isi > 0 && !Demo::terpasang() && !$this->option('paksa')) {
            /*
             * The dangerous case, and the reason this check exists.
             *
             * Data with no demo marker was put there by somebody, and this
             * command would destroy it without asking. Refusing by default costs
             * one flag; the alternative costs their work.
             */
            $this->newLine();
            $this->error("Basis data ini sudah berisi {$isi} baris data akademik dan bukan pasangan demo.");
            $this->line('Kalau memang ingin menghapusnya, ulangi dengan --paksa.');

            return self::FAILURE;
        }

        if (!$this->option('paksa') && !$this->confirm('Lanjutkan?', false)) {
            $this->line('Dibatalkan. Tidak ada yang diubah.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->call('migrate:fresh', ['--force' => true]);

        // Roles and permissions belong to the application, not to the demo —
        // an install without them cannot log anybody in.
        $this->call('db:seed', ['--class' => RolePermissionSeeder::class, '--force' => true]);
        $this->call('db:seed', ['--class' => DemoCampusSeeder::class, '--force' => true]);

        $this->newLine();
        $this->info('Kampus demo terpasang. Hapus lagi dengan: php artisan openacademic:demo-hapus');

        return self::SUCCESS;
    }

    /**
     * A rough count of academic rows, only to decide whether to stop and ask.
     *
     * Deliberately not exhaustive — it needs to answer "is anything here?", and
     * students, staff and lecturers are enough to answer that for any database
     * worth protecting.
     */
    private function cacahIsi(): int
    {
        $jumlah = 0;

        foreach (['mahasiswa', 'dosen', 'staff'] as $tabel) {
            if (Schema::hasTable($tabel)) {
                $jumlah += DB::table($tabel)->count();
            }
        }

        return $jumlah;
    }
}

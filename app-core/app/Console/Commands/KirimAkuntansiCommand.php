<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\StatusDokumenAkuntansi;
use App\Models\Akuntansi\DokumenAkuntansi;
use App\Services\Akuntansi\Contracts\AkuntansiClientInterface;
use App\Services\Akuntansi\PengirimAkuntansi;
use App\Support\Akuntansi;
use Illuminate\Console\Command;

/**
 * Drains the accounting outbox.
 *
 * Scheduled every few minutes, and safe to run by hand after somebody fixes a
 * chart-of-accounts mistake and requeues the documents it broke.
 */
class KirimAkuntansiCommand extends Command
{
    protected $signature = 'openacademic:kirim-akuntansi
        {--batas= : Jumlah dokumen maksimum sekali jalan}
        {--kering : Tampilkan antrean tanpa mengirim apa pun}';

    protected $description = 'Mengirim dokumen akuntansi yang mengantre ke Easy Accounting';

    public function handle(PengirimAkuntansi $pengirim, AkuntansiClientInterface $klien): int
    {
        if (!Akuntansi::aktif()) {
            // Bukan kegagalan. Modulnya memang opsional, dan perintah yang keluar
            // dengan kode galat tiap lima menit karena fitur yang sengaja
            // dimatikan melatih semua orang mengabaikan keluarannya.
            $this->info(
                'Integrasi akuntansi nonaktif (AKUNTANSI_DRIVER=nonaktif). '
                .'Tidak ada dokumen yang dicatat maupun dikirim.',
            );

            return self::SUCCESS;
        }

        $driver = Akuntansi::driver();

        $this->line("Driver akuntansi: <options=bold>{$driver}</>");

        $menunggu = DokumenAkuntansi::siapKirim()->count();
        $gagal = DokumenAkuntansi::where('status', StatusDokumenAkuntansi::Gagal->value)->count();

        if ($this->option('kering')) {
            // Claims nothing and sends nothing — the same contract as the
            // reminder command's dry run.
            $this->table(
                ['Siap kirim', 'Gagal (perlu tindakan)'],
                [[$menunggu, $gagal]],
            );

            return self::SUCCESS;
        }

        if ($menunggu === 0) {
            $this->info('Tidak ada dokumen yang menunggu.');

            return self::SUCCESS;
        }

        if (!$klien->tersedia()) {
            // Said plainly and treated as a non-failure: the documents stay
            // queued, and a cron entry that exits non-zero every five minutes
            // while the far side is down trains everybody to ignore it.
            $this->warn('Easy Accounting tidak merespons. Dokumen tetap mengantre.');

            return self::SUCCESS;
        }

        $hasil = $pengirim->jalankan(
            $this->option('batas') !== null ? (int) $this->option('batas') : null,
        );

        $this->table(
            ['Terkirim', 'Ditunda', 'Gagal'],
            [[$hasil['terkirim'], $hasil['ditunda'], $hasil['gagal']]],
        );

        if ($hasil['gagal'] > 0) {
            $this->warn(
                'Ada dokumen yang menyerah dan menunggu tindakan di layar Akuntansi. '
                .'Penyebab tersering: kode akun di config/akuntansi.php belum ada di Easy Accounting.',
            );
        }

        return self::SUCCESS;
    }
}

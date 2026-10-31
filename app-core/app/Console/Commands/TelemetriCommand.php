<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\System\InstallationPing;
use App\Services\Instalasi\TelemetriService;
use App\Support\Format;
use Illuminate\Console\Command;

/**
 * Shows what the installation ping would send and what it already sent
 * (ADR-043), and optionally sends the one for the running version.
 *
 * Read-only by default, so an operator can audit the ping without touching it.
 */
class TelemetriCommand extends Command
{
    protected $signature = 'openacademic:telemetri
        {--kirim : Kirim kabar untuk versi berjalan bila belum pernah terkirim}';

    protected $description = 'Menampilkan isi dan riwayat notifikasi instalasi ke Humanix';

    public function handle(TelemetriService $telemetri): int
    {
        $this->line('Persetujuan di layar : '.($telemetri->disetujui() ? 'ya' : 'tidak'));
        $this->line('HUMANIX_TELEMETRI    : '.($telemetri->diizinkanEnv() ? 'tidak mematikan' : 'false — dimatikan'));
        $this->line('Tujuan               : '.config('instalasi.telemetri.endpoint'));
        $this->newLine();

        $this->line('Isi yang akan dikirim:');
        $this->line((string) json_encode($telemetri->pratinjau(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $this->newLine();

        $riwayat = InstallationPing::query()->latest('id')->limit(10)->get();

        if ($riwayat->isEmpty()) {
            $this->line('Belum ada kabar yang pernah dikirim.');
        } else {
            $this->table(
                ['Versi', 'Kabar', 'Status', 'Percobaan', 'Terkirim', 'Galat'],
                $riwayat->map(fn (InstallationPing $p): array => [
                    $p->app_version,
                    $p->event->label(),
                    $p->status->label(),
                    $p->attempts,
                    $p->sent_at ? Format::tanggal($p->sent_at) : '—',
                    (string) $p->error,
                ])->all(),
            );
        }

        if (!$this->option('kirim')) {
            return self::SUCCESS;
        }

        if (!$telemetri->aktif()) {
            $this->warn('Tidak dikirim: persetujuan belum diberikan atau HUMANIX_TELEMETRI=false.');

            return self::SUCCESS;
        }

        $ping = $telemetri->laporkan();

        $this->info($ping === null
            ? 'Kabar untuk versi '.config('instalasi.versi').' sudah pernah terkirim; tidak ada yang dikirim ulang.'
            : 'Kabar diantrekan (status: '.$ping->status->label().').');

        return self::SUCCESS;
    }
}

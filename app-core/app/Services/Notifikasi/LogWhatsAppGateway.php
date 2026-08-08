<?php

declare(strict_types=1);

namespace App\Services\Notifikasi;

use App\Services\Notifikasi\Contracts\WhatsAppGatewayInterface;
use Illuminate\Support\Facades\Log;

/**
 * Writes what would have been sent to the application log.
 *
 * The development driver, and the one a campus should keep until a real
 * provider is configured and tested. It makes the message visible without
 * anybody's phone receiving a draft.
 *
 * Deliberately not a silent no-op: a channel that discards messages without
 * trace is indistinguishable from one that works, and that is exactly how a
 * campus discovers on results day that nothing has been going out.
 */
class LogWhatsAppGateway implements WhatsAppGatewayInterface
{
    public function kirim(string $nomor, string $pesan): bool
    {
        Log::info('WhatsApp (driver log — tidak benar-benar terkirim).', [
            'nomor' => $this->samarkan($nomor),
            'pesan' => $pesan,
        ]);

        return true;
    }

    public function nama(): string
    {
        return 'log';
    }

    /**
     * Logs are read by more people than the database is, and are shipped to
     * aggregators. A full phone number in one is personal data leaving the
     * system by accident.
     */
    private function samarkan(string $nomor): string
    {
        return strlen($nomor) <= 4
            ? str_repeat('*', strlen($nomor))
            : substr($nomor, 0, 4).str_repeat('*', max(0, strlen($nomor) - 8)).substr($nomor, -4);
    }
}

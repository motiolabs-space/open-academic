<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use App\Notifications\Notifikasi;
use App\Services\Notifikasi\Contracts\WhatsAppGatewayInterface;
use Illuminate\Notifications\Notification;

/**
 * Delivers a notification over WhatsApp, when a campus has opted in.
 *
 * Off unless `notifikasi.whatsapp.kategori` names the category. Empty by
 * default, which means no message leaves through here on a fresh installation —
 * see Preferensi::kanalUntuk().
 *
 * The message is the title and the summary, nothing more. WhatsApp is read on a
 * lock screen; anything longer is truncated by the client, and the sentence that
 * gets cut is always the one carrying the deadline.
 */
class WhatsAppChannel
{
    public function __construct(private readonly WhatsAppGatewayInterface $gateway) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (!$notification instanceof Notifikasi) {
            return;
        }

        $nomor = $this->nomor($notifiable);

        if ($nomor === null) {
            return;
        }

        $this->gateway->kirim(
            $nomor,
            $notification->judul($notifiable)."\n\n".$notification->ringkasan($notifiable),
        );
    }

    /**
     * Indonesian numbers arrive written half a dozen ways — 08xx, +62 8xx,
     * 62-8xx, with spaces. Providers want one of them.
     */
    private function nomor(object $notifiable): ?string
    {
        $mentah = preg_replace('/\D+/', '', (string) ($notifiable->telepon ?? ''));

        if (blank($mentah)) {
            return null;
        }

        if (str_starts_with($mentah, '0')) {
            return '62'.substr($mentah, 1);
        }

        return str_starts_with($mentah, '62') ? $mentah : '62'.$mentah;
    }
}

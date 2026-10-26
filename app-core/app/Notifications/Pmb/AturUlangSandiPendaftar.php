<?php

declare(strict_types=1);

namespace App\Notifications\Pmb;

use App\Models\Pmb\PmbAkun;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Config;

/**
 * A password reset link for an applicant account.
 *
 * The framework's version links to a `password.reset` route that does not
 * exist here; the campus portals have no self-service reset at all.
 *
 * @property string $token
 */
class AturUlangSandiPendaftar extends ResetPassword implements ShouldQueue
{
    use Queueable;

    /** @param PmbAkun $notifiable */
    protected function resetUrl($notifiable): string
    {
        // The token only. The address is typed on the form rather than carried
        // in the query string, where it would land in proxy and server logs.
        return route('pmb.sandi.atur-ulang', ['token' => $this->token]);
    }

    /** @param string $url */
    protected function buildMailMessage($url): MailMessage
    {
        return (new MailMessage)
            ->subject(__('pmb.sandi.surel_subjek'))
            ->line(__('pmb.sandi.surel_baris'))
            ->action(__('pmb.sandi.surel_tombol'), $url)
            ->line(__('pmb.sandi.surel_abaikan', [
                'menit' => (int) Config::get('auth.passwords.pendaftar.expire', 60),
            ]));
    }
}

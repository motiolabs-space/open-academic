<?php

declare(strict_types=1);

namespace App\Notifications\Pmb;

use App\Models\Pmb\PmbAkun;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;

/**
 * The link that proves an applicant owns the address they registered with.
 *
 * Pointed at the admission portal's own route and keyed by the account's
 * UUID, not by the framework's `verification.verify` and an auto-increment id:
 * this application has no such route, and the id would count applicants.
 */
class VerifikasiSurelPendaftar extends VerifyEmail implements ShouldQueue
{
    use Queueable;

    /** @param PmbAkun $notifiable */
    protected function verificationUrl($notifiable): string
    {
        return URL::temporarySignedRoute(
            'pmb.verifikasi.verify',
            Carbon::now()->addMinutes((int) Config::get('auth.verification.expire', 60)),
            [
                'akun' => $notifiable->uuid,
                'hash' => sha1($notifiable->getEmailForVerification()),
            ],
        );
    }

    /** @param string $url */
    protected function buildMailMessage($url): MailMessage
    {
        return (new MailMessage)
            ->subject(__('pmb.verifikasi.surel_subjek'))
            ->line(__('pmb.verifikasi.surel_baris'))
            ->action(__('pmb.verifikasi.surel_tombol'), $url)
            ->line(__('pmb.verifikasi.surel_kedaluwarsa', [
                'menit' => (int) Config::get('auth.verification.expire', 60),
            ]));
    }
}

<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\KategoriNotifikasi;
use App\Services\Notifikasi\Preferensi;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

/**
 * The shape every notification in this system takes.
 *
 * Subclasses declare four things — category, title, one-line summary, and where
 * to go — and nothing else. Rendering to the in-app record and to email happens
 * once, here, so the two can never drift into saying different things about the
 * same event.
 *
 * Two properties on this class are load-bearing:
 *
 *  - **ShouldQueue.** A campus announcing five thousand invoices must not do it
 *    inside the request that pressed the button, and an unreachable mail server
 *    must not become a failed enrolment.
 *
 *  - **SerializesModels.** Models travel as identifiers, so the worker re-reads
 *    them rather than acting on a copy taken minutes earlier.
 *
 * The third property that matters — dispatch only after the surrounding
 * transaction commits — is set once on the queue connection in config/queue.php
 * rather than per class. It is true of every queued job here, not just of
 * notifications, and a per-class flag is one somebody eventually forgets.
 */
abstract class Notifikasi extends Notification implements ShouldQueue
{
    use Queueable;

    /*
     * Models on a notification travel as identifiers, not as copies.
     *
     * Two reasons, and the second is the one that bites. A queued payload
     * carrying whole models is fat, and it is *stale* — the row is read when the
     * message is written, then re-read minutes later when the worker runs. An
     * invoice that was paid in between would still be announced as outstanding.
     */
    use SerializesModels;

    /**
     * A notification about something that no longer exists is dropped.
     *
     * The alternative is a job that fails forever because a cancelled defence
     * was deleted, filling the failed queue with noise nobody can act on.
     *
     * @var bool
     */
    public $deleteWhenMissingModels = true;

    abstract public function kategori(): KategoriNotifikasi;

    abstract public function judul(object $penerima): string;

    /** One sentence. It is the whole message on a phone notification list. */
    abstract public function ringkasan(object $penerima): string;

    /** Where the recipient goes to act on this, if anywhere. */
    public function tautan(object $penerima): ?string
    {
        return null;
    }

    /** Matches the chip tones used across the interface. */
    public function tone(): string
    {
        return 'info';
    }

    /**
     * The label on the button in the email. Ignored when tautan() is null.
     */
    public function ajakan(): string
    {
        return 'Buka Portal';
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return app(Preferensi::class)->kanalUntuk($notifiable, $this->kategori());
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'kategori' => $this->kategori()->value,
            'judul' => $this->judul($notifiable),
            'ringkasan' => $this->ringkasan($notifiable),
            'tautan' => $this->tautan($notifiable),
            'tone' => $this->tone(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $pesan = (new MailMessage)
            ->subject($this->judul($notifiable))
            ->greeting('Halo '.($notifiable->nama ?? '').',')
            ->line($this->ringkasan($notifiable));

        $tautan = $this->tautan($notifiable);

        if ($tautan !== null) {
            $pesan->action($this->ajakan(), $tautan);
        }

        /*
         * No "unsubscribe" line on mandatory categories, because there is no
         * such switch and telling someone there is would be a lie they only
         * discover after hunting for it.
         */
        return $this->kategori()->wajib()
            ? $pesan->line('Pemberitahuan ini bagian dari catatan akademik Anda dan selalu dikirim.')
            : $pesan->line('Anda dapat mengatur pemberitahuan seperti ini pada halaman Preferensi Notifikasi.');
    }
}

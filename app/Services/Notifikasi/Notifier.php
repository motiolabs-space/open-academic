<?php

declare(strict_types=1);

namespace App\Services\Notifikasi;

use App\Notifications\Notifikasi;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * The one place notifications are sent from.
 *
 * Everything here exists to enforce a single rule: **announcing an event must
 * never be able to undo it.** A study plan was approved, an invoice was issued,
 * a defence was scheduled — those happened. If the mail server is unreachable or
 * a template has a typo, the right outcome is a logged failure and a silent
 * user, not a rolled-back approval.
 *
 * That is why every send is wrapped. Laravel would normally let a broken
 * notification bubble out of the service that triggered it, and the caller sits
 * inside a transaction.
 */
class Notifier
{
    /** Sends to one recipient, or does nothing when there is nobody to tell. */
    public function kirim(?object $penerima, Notifikasi $notifikasi): void
    {
        if ($penerima === null) {
            return;
        }

        $this->kirimBanyak([$penerima], $notifikasi);
    }

    /**
     * Sends the same notification to several recipients.
     *
     * @param iterable<int, object> $penerima
     */
    public function kirimBanyak(iterable $penerima, Notifikasi $notifikasi): void
    {
        $daftar = collect($penerima)->filter()->values();

        if ($daftar->isEmpty()) {
            return;
        }

        try {
            Notification::send($daftar, $notifikasi);
        } catch (Throwable $e) {
            // Deliberately swallowed. See the class docblock: the event already
            // happened, and a failed announcement must not unmake it.
            Log::error('Pengiriman notifikasi gagal.', [
                'notifikasi' => $notifikasi::class,
                'penerima' => $daftar->count(),
                'galat' => $e->getMessage(),
            ]);

            report($e);
        }
    }

    /**
     * Sends only if this exact reminder has not gone out before.
     *
     * Deadline reminders run nightly and see the same overdue invoice every
     * night. Sending it every night is not thoroughness — it trains people to
     * ignore the channel, and then the one message that mattered is ignored too.
     *
     * The key claim is made first and the send follows, so a crash mid-send
     * loses a message rather than producing a duplicate. That is the safer of
     * the two failures here: a missed reminder is recoverable by a human, a
     * channel nobody reads is not.
     *
     * @return bool whether it was sent now
     */
    public function kirimSekali(object $penerima, string $kunci, Notifikasi $notifikasi): bool
    {
        if (!$this->klaim($penerima, $kunci)) {
            return false;
        }

        $this->kirim($penerima, $notifikasi);

        return true;
    }

    /** Forgets a claimed key, so the reminder may fire again. */
    public function lupakan(object $penerima, string $kunci): void
    {
        DB::table('notifikasi_kunci')
            ->where('notifiable_type', $penerima->getMorphClass())
            ->where('notifiable_id', $penerima->getKey())
            ->where('kunci', $kunci)
            ->delete();
    }

    /**
     * Claims a reminder key, atomically.
     *
     * The unique index does the work; two schedulers running at once cannot both
     * win. Catching the violation rather than checking-then-inserting is the
     * whole point — the check-then-insert version has a window between the two.
     */
    private function klaim(object $penerima, string $kunci): bool
    {
        try {
            DB::table('notifikasi_kunci')->insert([
                'notifiable_type' => $penerima->getMorphClass(),
                'notifiable_id' => $penerima->getKey(),
                'kunci' => $kunci,
                'created_at' => now(),
            ]);

            return true;
        } catch (UniqueConstraintViolationException) {
            // Somebody — or some other scheduler — got here first.
            //
            // Caught by Laravel's own exception type rather than by sniffing
            // SQLSTATE codes: each engine reports this differently, and the
            // framework already does that translation. A hand-rolled code list
            // is a portability bug that only appears on the engine nobody
            // developed against.
            return false;
        }
    }

    /**
     * Whether the notification tables have been migrated.
     *
     * Consulted by the demo seeder and by installations mid-upgrade; the rest of
     * the application may assume they exist.
     */
    public function siap(): bool
    {
        return Schema::hasTable('notifications');
    }
}

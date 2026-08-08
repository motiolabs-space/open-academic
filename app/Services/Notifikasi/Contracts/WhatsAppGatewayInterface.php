<?php

declare(strict_types=1);

namespace App\Services\Notifikasi\Contracts;

/**
 * Sending one WhatsApp message.
 *
 * WhatsApp is the channel Indonesian campuses actually reach students on —
 * email arrives, eventually, and is read by a minority. So the seam exists.
 *
 * No provider adapter ships with Open Academic, for the same reason none ships
 * for payments: every provider has its own account model, its own template
 * approval process, and its own per-message price, and guessing which one a
 * campus uses would produce an adapter nobody can run. Writing one against this
 * interface is a small, contained job — see docs/NOTIFIKASI.md.
 *
 * Implementations must not throw on delivery failure. Notifier already treats a
 * failed send as a logged non-event, and an exception here would be swallowed
 * there anyway; returning false keeps the reason visible in the log.
 */
interface WhatsAppGatewayInterface
{
    /**
     * @param string $nomor E.164 without the plus, e.g. 6281234567890
     * @return bool whether the provider accepted the message
     */
    public function kirim(string $nomor, string $pesan): bool;

    /** A human-readable name for the driver, used in logs and diagnostics. */
    public function nama(): string;
}

<?php

declare(strict_types=1);

namespace App\Services\Keuangan\Contracts;

use App\Models\Keuangan\Pembayaran;
use App\Models\Keuangan\Tagihan;

/**
 * The boundary between Open Academic and whoever moves the money.
 *
 * Every gateway lives behind this so a campus can switch provider without the
 * finance screens knowing, and so the test suite never talks to a real payment
 * network. CLAUDE.md rule 6 requires it for all external I/O; money is the case
 * where it matters most, because a test that accidentally charges somebody is
 * not a test failure that can be undone.
 */
interface PaymentGatewayInterface
{
    /**
     * Opens a payment for an invoice and returns the record to show the student.
     *
     * Implementations must be safe to call twice for the same invoice: a
     * student who reloads the payment page must not end up with two virtual
     * accounts for one debt.
     */
    public function buatPembayaran(Tagihan $tagihan, int $nominal, ?string $channel = null): Pembayaran;

    /**
     * Interprets a notification from the provider.
     *
     * Returns the updated payment, or null when the notification refers to
     * something this installation does not know about — which is normal traffic
     * on a shared merchant account, not an error.
     *
     * @param array<string, mixed> $payload
     */
    public function tanganiNotifikasi(array $payload): ?Pembayaran;

    /**
     * Whether a notification really came from the provider.
     *
     * Separate from handling it on purpose. A settlement notification is an
     * instruction to mark a debt paid; accepting an unverified one means
     * anybody who can reach the endpoint can settle any invoice on campus.
     *
     * @param array<string, mixed> $payload
     */
    public function verifikasiNotifikasi(array $payload): bool;

    /** Identifier stored on each payment row, so history stays readable after a switch. */
    public function nama(): string;
}

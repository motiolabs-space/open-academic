<?php

declare(strict_types=1);

namespace App\Services\Akuntansi\Contracts;

use App\Services\Akuntansi\HasilKirim;

/**
 * The accounting system, as this application needs it.
 *
 * One method that matters. Every document — contact, product, invoice, journal
 * — is a POST to a path with a payload and an idempotency key, so modelling
 * each as its own method would be four copies of the same HTTP call differing
 * only in a string.
 *
 * Behind a contract for the usual reason (CLAUDE.md rule 6), and here for a
 * sharper one: the test suite must never be able to post a journal entry into
 * somebody's real books.
 */
interface AkuntansiClientInterface
{
    /**
     * Posts one document.
     *
     * Implementations must treat `$kunciIdempotensi` as binding: the same key
     * twice returns the first result rather than creating a second document.
     * easyERP enforces this server-side per tenant; the fake enforces it in
     * memory so tests exercise the same guarantee.
     *
     * Must not throw on a refusal from the far side — a rejected document is an
     * outcome to record, not an exception to unwind a billing run with. Network
     * failures are also returned as a failed result, for the same reason.
     *
     * @param array<string, mixed> $payload
     */
    public function kirim(string $endpoint, array $payload, string $kunciIdempotensi): HasilKirim;

    /** Whether the service answers at all — used by the monitor screen. */
    public function tersedia(): bool;
}

<?php

declare(strict_types=1);

namespace App\Services\Feeder\Contracts;

use App\Services\Feeder\FeederResponse;

/**
 * The Neo Feeder web service, as this application needs it.
 *
 * Everything that talks to PDDIKTI goes through this contract so the whole
 * sync module can be exercised without a Feeder installation — the fake
 * implementation is what the test suite and the demo campus use.
 */
interface FeederClientInterface
{
    /** A valid session token, obtained and cached as needed. */
    public function token(): string;

    /** @param array<string, mixed> $payload */
    public function request(string $act, array $payload = []): FeederResponse;

    /** @param array<string, mixed> $record */
    public function insert(string $act, array $record): FeederResponse;

    /**
     * @param array<string, mixed> $key which row to change
     * @param array<string, mixed> $record the new values
     */
    public function update(string $act, array $key, array $record): FeederResponse;

    /** @param array<string, mixed> $filter */
    public function get(string $act, array $filter = [], int $limit = 0, int $offset = 0): FeederResponse;

    /** Whether the service answers at all — used by the monitor screen. */
    public function tersedia(): bool;
}

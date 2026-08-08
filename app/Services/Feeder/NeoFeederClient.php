<?php

declare(strict_types=1);

namespace App\Services\Feeder;

use App\Exceptions\FeederException;
use App\Services\Feeder\Contracts\FeederClientInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Talks to the on-premise Neo Feeder web service.
 *
 * Feeder is a single JSON endpoint dispatching on an `act` name. Two of its
 * habits shape this class:
 *
 *  - It answers HTTP 200 for failures too, so success is decided by
 *    `error_code`, never by the status line.
 *  - Tokens expire silently. Rather than pre-emptively refreshing on a timer we
 *    retry once on an auth-shaped rejection with a freshly minted token, which
 *    survives a Feeder restart mid-sync without a stampede of token requests.
 */
class NeoFeederClient implements FeederClientInterface
{
    private const CACHE_KEY = 'feeder.token';

    /** Feeder error codes that mean "your token is no longer good". */
    private const KODE_TOKEN_KEDALUWARSA = [401, 403, 1001];

    public function token(): string
    {
        $token = Cache::get(self::CACHE_KEY);

        if (is_string($token) && $token !== '') {
            return $token;
        }

        return $this->tokenBaru();
    }

    public function request(string $act, array $payload = [], bool $bolehUlangToken = true): FeederResponse
    {
        $response = $this->kirim(array_merge(['act' => $act, 'token' => $this->token()], $payload));

        if ($response->gagal() && $bolehUlangToken && $this->tokenKedaluwarsa($response)) {
            Cache::forget(self::CACHE_KEY);
            $this->tokenBaru();

            return $this->request($act, $payload, bolehUlangToken: false);
        }

        return $response;
    }

    public function insert(string $act, array $record): FeederResponse
    {
        return $this->request($act, ['record' => $record]);
    }

    public function update(string $act, array $key, array $record): FeederResponse
    {
        return $this->request($act, ['key' => $key, 'record' => $record]);
    }

    public function get(string $act, array $filter = [], int $limit = 0, int $offset = 0): FeederResponse
    {
        $payload = [];

        if ($filter !== []) {
            $payload['filter'] = $this->rangkaiFilter($filter);
        }

        if ($limit > 0) {
            $payload['limit'] = $limit;
            $payload['offset'] = $offset;
        }

        return $this->request($act, $payload);
    }

    public function tersedia(): bool
    {
        try {
            $this->tokenBaru();

            return true;
        } catch (FeederException $e) {
            Log::warning('Neo Feeder tidak tersedia', ['pesan' => $e->getMessage()]);

            return false;
        }
    }

    private function tokenBaru(): string
    {
        $response = $this->kirim([
            'act' => 'GetToken',
            'username' => config('feeder.credentials.username'),
            'password' => config('feeder.credentials.password'),
        ]);

        if ($response->gagal()) {
            throw FeederException::gagalToken($response->errorDesc);
        }

        $token = data_get($response->data, 'token');

        if (!is_string($token) || $token === '') {
            throw FeederException::gagalToken('respons tidak memuat token.');
        }

        // Expire our copy slightly early so a request never leaves with a token
        // that dies in flight.
        Cache::put(self::CACHE_KEY, $token, (int) config('feeder.token_ttl') - 60);

        return $token;
    }

    /** @param array<string, mixed> $body */
    private function kirim(array $body): FeederResponse
    {
        $url = (string) config('feeder.base_url');

        try {
            $response = Http::timeout((int) config('feeder.timeout'))
                ->retry(
                    (int) config('feeder.retry_times'),
                    (int) config('feeder.retry_sleep_ms'),

                    // Retry transport failures only. A Feeder rejection is an
                    // answer, and repeating it just delays the report.
                    throw: false,
                )
                ->asJson()
                ->acceptJson()
                ->post($url, $body);
        } catch (ConnectionException $e) {
            throw FeederException::tidakTersambung($url, $e->getMessage());
        }

        if ($response->failed() && $response->json() === null) {
            throw FeederException::tidakTersambung($url, 'HTTP '.$response->status());
        }

        return FeederResponse::dariBody((array) $response->json());
    }

    private function tokenKedaluwarsa(FeederResponse $response): bool
    {
        return in_array($response->errorCode, self::KODE_TOKEN_KEDALUWARSA, true)
            || str_contains(mb_strtolower($response->errorDesc), 'token');
    }

    /**
     * Feeder filters are a SQL-ish string, not a structure.
     *
     * @param array<string, mixed> $filter
     */
    private function rangkaiFilter(array $filter): string
    {
        return collect($filter)
            ->map(fn (mixed $nilai, string $kolom): string => sprintf(
                "%s = '%s'",
                $kolom,
                str_replace("'", "''", (string) $nilai),
            ))
            ->implode(' AND ');
    }
}

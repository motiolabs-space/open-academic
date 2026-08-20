<?php

declare(strict_types=1);

namespace App\Services\Feeder;

use App\Services\Feeder\Contracts\FeederClientInterface;
use Illuminate\Support\Str;

/**
 * A Neo Feeder stand-in for tests and the demo campus.
 *
 * It behaves like the real thing in the ways that matter to the code above it:
 * inserts return a generated Feeder id, reference pulls return fixture rows,
 * and a caller can arm a specific failure to exercise the ledger's error path.
 * It deliberately does *not* validate payloads — that is the pre-flight
 * validator's job, and duplicating those rules here would let a bug hide behind
 * agreement between two copies of the same logic.
 */
class FakeFeederClient implements FeederClientInterface
{
    /** @var array<int, array{act: string, payload: array<string, mixed>}> */
    public array $riwayat = [];

    /** @var array<string, FeederResponse> act => canned reply */
    private array $balasan = [];

    private bool $tersedia = true;

    public function __construct(private readonly string $fixturePath = '') {}

    /* ---------------------------------------------------------------------
     | Test controls
     |-------------------------------------------------------------------- */

    /** Makes the next call to $act fail with a Feeder-style rejection. */
    public function tolak(string $act, int $kode = 4, string $desc = 'Data tidak valid'): self
    {
        $this->balasan[$act] = new FeederResponse($kode, $desc, null);

        return $this;
    }

    /** @param array<int, array<string, mixed>>|array<string, mixed> $data */
    public function balas(string $act, array $data): self
    {
        $this->balasan[$act] = new FeederResponse(0, '', $data);

        return $this;
    }

    public function matikan(): self
    {
        $this->tersedia = false;

        return $this;
    }

    public function dipanggil(string $act): bool
    {
        return collect($this->riwayat)->contains(fn (array $baris): bool => $baris['act'] === $act);
    }

    public function jumlahPanggilan(string $act): int
    {
        return collect($this->riwayat)->where('act', $act)->count();
    }

    /* ---------------------------------------------------------------------
     | Contract
     |-------------------------------------------------------------------- */

    public function token(): string
    {
        return 'token-palsu';
    }

    public function request(string $act, array $payload = []): FeederResponse
    {
        $this->riwayat[] = ['act' => $act, 'payload' => $payload];

        if (isset($this->balasan[$act])) {
            return $this->balasan[$act];
        }

        if (str_starts_with($act, 'Get')) {
            return new FeederResponse(0, '', $this->fixture($act));
        }

        // Insert/Update: mirror Feeder by handing back a generated identifier.
        return new FeederResponse(0, '', [
            ['id_registrasi_mahasiswa' => (string) Str::uuid()],
        ]);
    }

    public function insert(string $act, array $record): FeederResponse
    {
        return $this->request($act, ['record' => $record]);
    }

    public function update(string $act, array $key, array $record): FeederResponse
    {
        return $this->request($act, ['key' => $key, 'record' => $record]);
    }

    /**
     * Reads rows back, honouring limit and offset.
     *
     * The paging is not decoration. A double that always answers the whole set
     * would let a caller that never advances its offset — or one that loops
     * forever because the last page is always full — pass every test it has.
     */
    public function get(string $act, array $filter = [], int $limit = 0, int $offset = 0): FeederResponse
    {
        $response = $this->request($act, ['filter' => $filter, 'limit' => $limit, 'offset' => $offset]);

        if ($limit <= 0 || $response->gagal()) {
            return $response;
        }

        return new FeederResponse(
            $response->errorCode,
            $response->errorDesc,
            array_values(array_slice($response->rows(), $offset, $limit)),
            $response->raw,
        );
    }

    public function tersedia(): bool
    {
        return $this->tersedia;
    }

    /**
     * Fixture rows for a Get action, or an empty set when none is provided.
     *
     * @return array<int, array<string, mixed>>
     */
    private function fixture(string $act): array
    {
        $berkas = ($this->fixturePath ?: base_path('tests/Fixtures/feeder')).'/'.$act.'.json';

        if (!is_file($berkas)) {
            return [];
        }

        return json_decode((string) file_get_contents($berkas), true, flags: JSON_THROW_ON_ERROR);
    }
}

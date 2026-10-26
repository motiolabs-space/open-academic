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

    /** @var array<string, array<int, array<string, mixed>>> act => rows a Get answers, filtered */
    private array $isi = [];

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

    /**
     * Rows a Get act answers, narrowed by the request's filter.
     *
     * Unlike balas(), the filter is honoured — equality on every field it
     * names, which is all the live client ever sends. A caller that forgets
     * to filter by programme would otherwise pass while reading the whole
     * campus.
     *
     * @param array<int, array<string, mixed>> $rows
     */
    public function isi(string $act, array $rows): self
    {
        $this->isi[$act] = array_values($rows);

        return $this;
    }

    /**
     * Students PDDIKTI already holds, as the two lists adoption reads.
     *
     * Each person is one registration: id_mahasiswa, id_registrasi_mahasiswa,
     * nim, nik, nama_mahasiswa, tanggal_lahir, id_prodi, id_periode_masuk.
     * The enrolment list gets the NIM, the person list the NIK — the same
     * split as the live service, so a matcher that reads only one of them
     * fails here too.
     *
     * @param array<int, array<string, mixed>> $orang
     */
    public function mahasiswaTerdaftar(array $orang): self
    {
        $this->isi((string) config('feeder.adopt.riwayat.get_action'), array_map(fn (array $o): array => [
            'id_registrasi_mahasiswa' => $o['id_registrasi_mahasiswa'],
            'id_mahasiswa' => $o['id_mahasiswa'],
            'nim' => $o['nim'],
            'nama_mahasiswa' => $o['nama_mahasiswa'],
            'id_prodi' => $o['id_prodi'],
            'id_periode_masuk' => $o['id_periode_masuk'],
        ], $orang));

        return $this->isi((string) config('feeder.adopt.biodata.get_action'), array_map(fn (array $o): array => [
            'id_mahasiswa' => $o['id_mahasiswa'],
            'nama_mahasiswa' => $o['nama_mahasiswa'],
            'nik' => $o['nik'] ?? null,
            'tanggal_lahir' => $o['tanggal_lahir'] ?? null,
            'id_prodi' => $o['id_prodi'],
            'id_periode' => $o['id_periode_masuk'],
        ], $orang));
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

        if (isset($this->isi[$act])) {
            return new FeederResponse(0, '', $this->saring($this->isi[$act], (array) ($payload['filter'] ?? [])));
        }

        if (str_starts_with($act, 'Get')) {
            return new FeederResponse(0, '', $this->fixture($act));
        }

        // Insert/Update: mirror Feeder by handing back a generated identifier,
        // under the name the real service uses for that entity.
        return new FeederResponse(0, '', [
            [$this->namaId($act) => (string) Str::uuid()],
        ]);
    }

    /**
     * The identifier field Feeder answers an insert of this act with.
     *
     * Only the entities whose reply is read back under a *different* name
     * than the default need listing: a mapper that stores `id_bobot_nilai`
     * would otherwise be handed `id_registrasi_mahasiswa` and store that.
     */
    private function namaId(string $act): string
    {
        return match ($act) {
            'InsertSkalaNilaiProdi' => 'id_bobot_nilai',
            default => 'id_registrasi_mahasiswa',
        };
    }

    public function insert(string $act, array $record): FeederResponse
    {
        return $this->request($act, ['record' => $record]);
    }

    public function update(string $act, array $key, array $record): FeederResponse
    {
        return $this->request($act, ['key' => $key, 'record' => $record]);
    }

    public function delete(string $act, array $key): FeederResponse
    {
        return $this->request($act, ['key' => $key]);
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
     * @param array<int, array<string, mixed>> $rows
     * @param array<string, mixed> $filter
     * @return array<int, array<string, mixed>>
     */
    private function saring(array $rows, array $filter): array
    {
        return array_values(array_filter($rows, function (array $baris) use ($filter): bool {
            foreach ($filter as $kolom => $nilai) {
                if ((string) ($baris[$kolom] ?? '') !== (string) $nilai) {
                    return false;
                }
            }

            return true;
        }));
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

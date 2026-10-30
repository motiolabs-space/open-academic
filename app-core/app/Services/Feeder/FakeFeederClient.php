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

    /** @var array<string, array{0: int, 1: string}> registration => refusal armed for its next claim */
    private array $tolakKlaim = [];

    private bool $tersedia = true;

    /** Which invented dictionary GetDictionary answers from; see kamus(). */
    private string $kamus = 'selaras';

    /**
     * Invented dictionaries, as changes to the mappers' own field lists.
     *
     * None of this is copied from a real Neo Feeder: the version labels name
     * the situations ROADMAP #52–#54 worry about, and the field names are
     * made up to exercise every kind of finding — a rename, a required field
     * we never send, a field we send that the build does not know, an act
     * the build does not serve. Real dictionaries stay in the campus's own
     * database (CLAUDE.md §10).
     *
     * Per act: `ganti` renames ours => theirs (required), `wajib` adds a
     * required field, `opsional` an optional one, `hapus` drops one of ours;
     * null means the act is not served at all.
     *
     * @var array<string, array<string, array<string, mixed>|null>>
     */
    private const KAMUS_FIKTIF = [
        'selaras' => [],
        '3.0.1' => [
            'InsertBiodataMahasiswa' => ['ganti' => ['telepon_seluler' => 'nomor_hp'], 'wajib' => ['id_wilayah']],
            'InsertMahasiswaLulusDO' => ['opsional' => ['tanggal_terbit_ijazah']],
        ],
        '3.1' => [
            'InsertBiodataMahasiswa' => ['ganti' => ['telepon_seluler' => 'nomor_hp'], 'wajib' => ['id_wilayah'], 'hapus' => ['kewarganegaraan']],
            'InsertMahasiswaLulusDO' => ['wajib' => ['tanggal_terbit_ijazah']],
            'InsertAktivitasMBKMFlagship' => null,
        ],
    ];

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
        $this->isi((string) config('feeder.adopt.riwayat.get_action'), array_map($this->barisRiwayat(...), $orang));

        return $this->isi((string) config('feeder.adopt.biodata.get_action'), array_map($this->barisBiodata(...), $orang));
    }

    /**
     * Past students PDDIKTI holds for the campus, unclaimed by its Feeder.
     *
     * Same shape as mahasiswaTerdaftar(), on one list. Claiming one through
     * the claim act moves it from this list to the two adoption reads, as
     * the live service does, so a test can follow a claim into adoption.
     * A row carrying `_tolak` is always refused, with that text as Feeder's
     * message — a record another campus has already claimed, say.
     *
     * @param array<int, array<string, mixed>> $orang
     */
    public function mahasiswaLampau(array $orang): self
    {
        return $this->isi((string) config('feeder.adopt.lampau.get_action'), array_map(fn (array $o): array => array_filter([
            'id_registrasi_mahasiswa' => $o['id_registrasi_mahasiswa'] ?? null,
            'id_mahasiswa' => $o['id_mahasiswa'] ?? null,
            'nim' => $o['nim'] ?? null,
            'nik' => $o['nik'] ?? null,
            'nama_mahasiswa' => $o['nama_mahasiswa'] ?? null,
            'tanggal_lahir' => $o['tanggal_lahir'] ?? null,
            'id_prodi' => $o['id_prodi'],
            'id_periode_masuk' => $o['id_periode_masuk'],
            '_tolak' => $o['_tolak'] ?? null,
        ], fn ($nilai): bool => $nilai !== null), $orang));
    }

    /** Makes the next claim of this registration fail; the one after succeeds. */
    public function tolakKlaim(string $idRegistrasi, int $kode = 100, string $desc = 'Gagal mengklaim mahasiswa lampau'): self
    {
        $this->tolakKlaim[$idRegistrasi] = [$kode, $desc];

        return $this;
    }

    /**
     * Which invented dictionary GetDictionary answers from: "selaras" (the
     * mappers' own fields, nothing to report), "3.0.1" or "3.1".
     */
    public function kamus(string $skenario): self
    {
        if (!array_key_exists($skenario, self::KAMUS_FIKTIF)) {
            throw new \InvalidArgumentException("Skenario kamus palsu tidak dikenal: {$skenario}");
        }

        $this->kamus = $skenario;

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

        if ($act === config('feeder.adopt.lampau.claim_action')) {
            return $this->klaimLampau((array) ($payload['key'] ?? []));
        }

        if ($act === config('feeder.kamus.act')) {
            return $this->balasKamus((string) ($payload[(string) config('feeder.kamus.parameter')] ?? ''));
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
            'InsertAktivitasMBKMFlagship' => 'id_aktivitas',
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
     * One act's dictionary from the armed scenario.
     *
     * Starts from the fields the act's mappers send — so "selaras" agrees
     * with the code as it stands — and applies the scenario's changes. The
     * shape (act name, then `record`, then a map of fields) is one of the
     * shapes KamusFeeder reads; the parser's own tests cover the others.
     */
    private function balasKamus(string $act): FeederResponse
    {
        $ubahan = self::KAMUS_FIKTIF[$this->kamus];
        $mappers = collect(FeederSyncService::MAPPERS)->map(fn (string $kelas) => app($kelas))
            ->filter(fn ($mapper): bool => $mapper->act() === $act);

        if ($mappers->isEmpty() || (array_key_exists($act, $ubahan) && $ubahan[$act] === null)) {
            return new FeederResponse(100, "Fungsi {$act} tidak ditemukan", null);
        }

        $medan = [];

        // A build's dictionary does not move when the campus edits its config,
        // so the names a campus sets are taken back to the defaults first.
        foreach ($mappers->flatMap(fn ($mapper): array => $mapper->medan())->unique() as $nama) {
            if ($nama === MedanFeeder::tanggalIjazah()) {
                continue;
            }

            $medan[$nama === MedanFeeder::hp() ? MedanFeeder::HP_BAWAAN : $nama] = ['tipe' => 'teks', 'wajib' => false];
        }

        $ubah = $ubahan[$act] ?? [];

        foreach ($ubah['ganti'] ?? [] as $kita => $mereka) {
            if (isset($medan[$kita])) {
                unset($medan[$kita]);
                $medan[$mereka] = ['tipe' => 'teks', 'wajib' => true];
            }
        }

        foreach ($ubah['hapus'] ?? [] as $nama) {
            unset($medan[$nama]);
        }

        foreach (['wajib' => true, 'opsional' => false] as $jenis => $wajib) {
            foreach ($ubah[$jenis] ?? [] as $nama) {
                $medan[$nama] = ['tipe' => 'teks', 'wajib' => $wajib];
            }
        }

        $data = [$act => ['record' => $medan]];
        $versi = $this->kamus === 'selaras' ? null : $this->kamus;

        return new FeederResponse(0, '', $data, array_filter(['error_code' => 0, 'versi' => $versi, 'data' => $data], fn ($v): bool => $v !== null));
    }

    /**
     * Claims one past student: off the past list, onto the adoption lists.
     *
     * @param array<string, mixed> $key
     */
    private function klaimLampau(array $key): FeederResponse
    {
        $reg = (string) ($key['id_registrasi_mahasiswa'] ?? '');

        if (isset($this->tolakKlaim[$reg])) {
            [$kode, $desc] = $this->tolakKlaim[$reg];
            unset($this->tolakKlaim[$reg]);

            return new FeederResponse($kode, $desc, null);
        }

        $daftar = (string) config('feeder.adopt.lampau.get_action');
        $posisi = collect($this->isi[$daftar] ?? [])->search(fn (array $b): bool => ($b['id_registrasi_mahasiswa'] ?? null) === $reg);

        if ($posisi === false) {
            return new FeederResponse(404, 'Data mahasiswa lampau tidak ditemukan', null);
        }

        $orang = $this->isi[$daftar][$posisi];

        if (filled($orang['_tolak'] ?? null)) {
            return new FeederResponse(1301, (string) $orang['_tolak'], null);
        }

        array_splice($this->isi[$daftar], $posisi, 1);
        $this->isi[(string) config('feeder.adopt.riwayat.get_action')][] = $this->barisRiwayat($orang);
        $this->isi[(string) config('feeder.adopt.biodata.get_action')][] = $this->barisBiodata($orang);

        return new FeederResponse(0, '', [['id_registrasi_mahasiswa' => $reg]]);
    }

    /**
     * @param array<string, mixed> $o
     * @return array<string, mixed>
     */
    private function barisRiwayat(array $o): array
    {
        return [
            'id_registrasi_mahasiswa' => $o['id_registrasi_mahasiswa'],
            'id_mahasiswa' => $o['id_mahasiswa'],
            'nim' => $o['nim'],
            'nama_mahasiswa' => $o['nama_mahasiswa'],
            'id_prodi' => $o['id_prodi'],
            'id_periode_masuk' => $o['id_periode_masuk'],
        ];
    }

    /**
     * @param array<string, mixed> $o
     * @return array<string, mixed>
     */
    private function barisBiodata(array $o): array
    {
        return [
            'id_mahasiswa' => $o['id_mahasiswa'],
            'nama_mahasiswa' => $o['nama_mahasiswa'],
            'nik' => $o['nik'] ?? null,
            'tanggal_lahir' => $o['tanggal_lahir'] ?? null,
            'id_prodi' => $o['id_prodi'],
            'id_periode' => $o['id_periode_masuk'],
        ];
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

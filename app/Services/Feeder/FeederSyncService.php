<?php

declare(strict_types=1);

namespace App\Services\Feeder;

use App\Enums\FeederSyncStatus;
use App\Exceptions\FeederException;
use App\Models\Akademik\TahunAkademik;
use App\Models\Feeder\FeederRef;
use App\Models\Feeder\FeederSyncLog;
use App\Services\Feeder\Contracts\FeederClientInterface;
use App\Services\Feeder\Mappers\AktivitasKuliahMapper;
use App\Services\Feeder\Mappers\FeederMapper;
use App\Services\Feeder\Mappers\KelasKuliahMapper;
use App\Services\Feeder\Mappers\KrsMapper;
use App\Services\Feeder\Mappers\MahasiswaMapper;
use App\Services\Feeder\Mappers\NilaiMapper;
use App\Services\Feeder\Mappers\RiwayatPendidikanMapper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Orchestrates PDDIKTI reporting.
 *
 * Three properties make this module worth having, and all three live here:
 *
 *  1. **Idempotent.** Each row's payload is hashed and compared against what
 *     Feeder last accepted. An unchanged row is recorded as Skipped, not sent
 *     again. A sync interrupted by a dropped connection, a browser close, or a
 *     server restart can always simply be re-run.
 *
 *  2. **Ordered.** An entity refuses to run until the entities it depends on
 *     have succeeded for the same term, because Feeder rejects a KRS whose
 *     class it has never heard of — and does so one row at a time.
 *
 *  3. **Accountable.** Every push, skip and failure writes a ledger row with
 *     the payload and the reason. When PDDIKTI and the campus disagree about
 *     what was reported, this table is the answer.
 */
class FeederSyncService
{
    public function __construct(
        private readonly FeederClientInterface $client,
        private readonly FeederValidator $validator,
    ) {}

    /** @return array<string, class-string<FeederMapper>> */
    public const MAPPERS = [
        'mahasiswa' => MahasiswaMapper::class,
        'riwayat_pendidikan' => RiwayatPendidikanMapper::class,
        'aktivitas_kuliah' => AktivitasKuliahMapper::class,
        'kelas_kuliah' => KelasKuliahMapper::class,
        'krs' => KrsMapper::class,
        'nilai' => NilaiMapper::class,
    ];

    /**
     * Pushes one entity for one term.
     *
     * @return array{entity: string, term: string, terkirim: int, dilewati: int, gagal: int, batch_validasi: string|null}
     */
    public function sinkronkan(string $entity, TahunAkademik $term, bool $lewatiValidasi = false): array
    {
        $this->pastikanAktif();

        $mapper = $this->mapper($entity);

        $this->pastikanDependensiSelesai($entity, $term);

        $batchValidasi = null;

        if (!$lewatiValidasi) {
            $hasil = $this->validator->periksa($entity, $term->kode, $mapper);
            $batchValidasi = $hasil['batch'];

            if ($hasil['error'] > 0) {
                throw FeederException::adaBarisTidakValid($entity, $hasil['error']);
            }
        }

        $terkirim = $dilewati = $gagal = 0;

        foreach ($mapper->rows($term->kode) as $model) {
            match ($this->kirimBaris($entity, $term, $mapper, $model)) {
                FeederSyncStatus::Success => $terkirim++,
                FeederSyncStatus::Skipped => $dilewati++,
                default => $gagal++,
            };
        }

        return [
            'entity' => $entity,
            'term' => $term->kode,
            'terkirim' => $terkirim,
            'dilewati' => $dilewati,
            'gagal' => $gagal,
            'batch_validasi' => $batchValidasi,
        ];
    }

    /** Retries the rows that previously failed, and only those. */
    public function ulangiYangGagal(string $entity, TahunAkademik $term): array
    {
        $mapper = $this->mapper($entity);

        $gagal = FeederSyncLog::query()
            ->entity($entity)
            ->where('tahun_akademik_id', $term->id)
            ->gagal()
            ->get();

        $berhasil = $masihGagal = 0;

        foreach ($gagal as $log) {
            $model = $log->local_type::find($log->local_id);

            if ($model === null) {
                continue;
            }

            $this->kirimBaris($entity, $term, $mapper, $model) === FeederSyncStatus::Success
                ? $berhasil++
                : $masihGagal++;
        }

        return ['diulang' => $gagal->count(), 'berhasil' => $berhasil, 'gagal' => $masihGagal];
    }

    /**
     * Pulls Feeder's reference tables.
     *
     * Always run before pushing anything: the codes a campus maps its enums to
     * belong to Feeder, and assuming they match ours is how a whole term gets
     * reported with the wrong status codes.
     *
     * @return array<string, int> ref type => rows stored
     */
    public function tarikReferensi(): array
    {
        $this->pastikanAktif();

        $hasil = [];

        foreach (config('feeder.references') as $tipe => $act) {
            $response = $this->client->get($act);

            if ($response->gagal()) {
                Log::warning('Gagal menarik referensi Feeder', ['tipe' => $tipe, 'pesan' => $response->errorDesc]);
                $hasil[$tipe] = 0;

                continue;
            }

            $jumlah = 0;

            foreach ($response->rows() as $baris) {
                $kode = $this->tebakKode($baris);

                if ($kode === null) {
                    continue;
                }

                FeederRef::updateOrCreate(
                    ['ref_type' => $tipe, 'code' => $kode],
                    [
                        'name' => (string) ($this->tebakNama($baris) ?? $kode),
                        'payload' => $baris,
                        'synced_at' => now(),
                    ],
                );

                $jumlah++;
            }

            $hasil[$tipe] = $jumlah;
        }

        return $hasil;
    }

    /** Whether the Feeder service answers right now. */
    public function sehat(): bool
    {
        return config('feeder.enabled') && $this->client->tersedia();
    }

    /* ---------------------------------------------------------------------
     | Internals
     |-------------------------------------------------------------------- */

    /**
     * Pushes one row and writes exactly one ledger entry for it.
     */
    private function kirimBaris(
        string $entity,
        TahunAkademik $term,
        FeederMapper $mapper,
        Model $model,
    ): FeederSyncStatus {
        $payload = $mapper->payload($model);
        $hash = FeederSyncLog::hashPayload($payload);
        $feederId = $mapper->feederId($model);

        // The idempotency check: unchanged payload, already accepted → skip.
        if ($feederId !== null && $this->hashTerakhirSukses($entity, $model) === $hash) {
            return $this->catat($entity, $term, $mapper, $model, $payload, $hash, FeederSyncStatus::Skipped);
        }

        try {
            $response = $feederId === null
                ? $this->client->insert($mapper->act(), $payload)
                : $this->client->update(
                    str_replace('Insert', 'Update', $mapper->act()),
                    $this->kunciUpdate($mapper, $feederId),
                    $payload,
                );
        } catch (FeederException $e) {
            return $this->catat(
                $entity, $term, $mapper, $model, $payload, $hash,
                FeederSyncStatus::Failed,
                (string) ($e->feederErrorCode ?? 'transport'),
                $e->getMessage(),
            );
        }

        if ($response->gagal()) {
            return $this->catat(
                $entity, $term, $mapper, $model, $payload, $hash,
                FeederSyncStatus::Failed,
                (string) $response->errorCode,
                $response->errorDesc,
            );
        }

        if ($feederId === null && ($baru = $response->feederId()) !== null) {
            $mapper->simpanFeederId($model, $baru);
        }

        return $this->catat($entity, $term, $mapper, $model, $payload, $hash, FeederSyncStatus::Success);
    }

    /** @param array<string, mixed> $payload */
    private function catat(
        string $entity,
        TahunAkademik $term,
        FeederMapper $mapper,
        Model $model,
        array $payload,
        string $hash,
        FeederSyncStatus $status,
        ?string $errorCode = null,
        ?string $errorMessage = null,
    ): FeederSyncStatus {
        FeederSyncLog::create([
            'entity' => $entity,
            'action' => $mapper->act(),
            'direction' => 'push',
            'local_type' => $model->getMorphClass(),
            'local_id' => $model->getKey(),
            'feeder_id' => $mapper->feederId($model->refresh()),
            'tahun_akademik_id' => $term->id,
            'payload_hash' => $hash,
            'payload' => $payload,
            'status' => $status,
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
            'attempts' => 1,
            'synced_at' => $status === FeederSyncStatus::Success ? now() : null,
        ]);

        return $status;
    }

    private function hashTerakhirSukses(string $entity, Model $model): ?string
    {
        return FeederSyncLog::query()
            ->entity($entity)
            ->where('local_type', $model->getMorphClass())
            ->where('local_id', $model->getKey())
            ->where('status', FeederSyncStatus::Success->value)
            ->latest('id')
            ->value('payload_hash');
    }

    /** @return array<string, string> */
    private function kunciUpdate(FeederMapper $mapper, string $feederId): array
    {
        return match ($mapper::class) {
            MahasiswaMapper::class => ['id_mahasiswa' => $feederId],
            RiwayatPendidikanMapper::class => ['id_registrasi_mahasiswa' => $feederId],
            KelasKuliahMapper::class => ['id_kelas_kuliah' => $feederId],
            default => ['id' => $feederId],
        };
    }

    private function pastikanAktif(): void
    {
        if (!config('feeder.enabled')) {
            throw FeederException::dinonaktifkan();
        }
    }

    /**
     * Refuses to push an entity whose prerequisites have not succeeded for this
     * term. Feeder would reject those rows one by one; failing fast with a
     * readable reason costs an operator minutes instead of an afternoon.
     */
    private function pastikanDependensiSelesai(string $entity, TahunAkademik $term): void
    {
        $belum = collect(config("feeder.entities.{$entity}.depends_on", []))
            ->reject(fn (string $dependensi): bool => FeederSyncLog::query()
                ->entity($dependensi)
                ->where('tahun_akademik_id', $term->id)
                ->where('status', FeederSyncStatus::Success->value)
                ->exists())
            ->values()
            ->all();

        if ($belum !== []) {
            throw FeederException::dependensiBelumSinkron($entity, $belum);
        }
    }

    private function mapper(string $entity): FeederMapper
    {
        if (!isset(self::MAPPERS[$entity])) {
            throw FeederException::entitasTidakDikenal($entity);
        }

        return app(self::MAPPERS[$entity]);
    }

    /** @param array<string, mixed> $baris */
    private function tebakKode(array $baris): ?string
    {
        foreach ($baris as $kunci => $nilai) {
            if (str_starts_with($kunci, 'id_') && filled($nilai)) {
                return (string) $nilai;
            }
        }

        return isset($baris['kode']) ? (string) $baris['kode'] : null;
    }

    /** @param array<string, mixed> $baris */
    private function tebakNama(array $baris): ?string
    {
        foreach ($baris as $kunci => $nilai) {
            if (str_starts_with($kunci, 'nama') && filled($nilai)) {
                return (string) $nilai;
            }
        }

        return null;
    }
}

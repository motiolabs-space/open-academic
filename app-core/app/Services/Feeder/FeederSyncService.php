<?php

declare(strict_types=1);

namespace App\Services\Feeder;

use App\Enums\FeederSyncStatus;
use App\Exceptions\FeederException;
use App\Models\Akademik\TahunAkademik;
use App\Models\Feeder\FeederRef;
use App\Models\Feeder\FeederSyncLog;
use App\Models\Sdm\Dosen;
use App\Services\Feeder\Contracts\FeederClientInterface;
use App\Services\Feeder\Mappers\AktivitasKuliahMapper;
use App\Services\Feeder\Mappers\AktivitasMbkmMapper;
use App\Services\Feeder\Mappers\AnggotaMbkmMapper;
use App\Services\Feeder\Mappers\AnggotaTugasAkhirMapper;
use App\Services\Feeder\Mappers\BimbingMbkmMapper;
use App\Services\Feeder\Mappers\BimbingTugasAkhirMapper;
use App\Services\Feeder\Mappers\DosenPengajarMapper;
use App\Services\Feeder\Mappers\FeederMapper;
use App\Services\Feeder\Mappers\KelasKuliahMapper;
use App\Services\Feeder\Mappers\KomponenEvaluasiMapper;
use App\Services\Feeder\Mappers\KonversiMbkmMapper;
use App\Services\Feeder\Mappers\KrsMapper;
use App\Services\Feeder\Mappers\KurikulumMapper;
use App\Services\Feeder\Mappers\LulusDoMapper;
use App\Services\Feeder\Mappers\MahasiswaMapper;
use App\Services\Feeder\Mappers\MataKuliahMapper;
use App\Services\Feeder\Mappers\MatkulKurikulumMapper;
use App\Services\Feeder\Mappers\NilaiKomponenMapper;
use App\Services\Feeder\Mappers\NilaiMapper;
use App\Services\Feeder\Mappers\PeriodePerkuliahanMapper;
use App\Services\Feeder\Mappers\PrestasiMapper;
use App\Services\Feeder\Mappers\RiwayatPendidikanMapper;
use App\Services\Feeder\Mappers\SkalaNilaiProdiMapper;
use App\Services\Feeder\Mappers\TransferKreditMapper;
use App\Services\Feeder\Mappers\TugasAkhirMapper;
use App\Services\Feeder\Mappers\UjiTugasAkhirMapper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
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
        'skala_nilai_prodi' => SkalaNilaiProdiMapper::class,
        'periode_perkuliahan' => PeriodePerkuliahanMapper::class,
        'kurikulum' => KurikulumMapper::class,
        'mata_kuliah' => MataKuliahMapper::class,
        'matkul_kurikulum' => MatkulKurikulumMapper::class,
        'mahasiswa' => MahasiswaMapper::class,
        'riwayat_pendidikan' => RiwayatPendidikanMapper::class,
        'aktivitas_kuliah' => AktivitasKuliahMapper::class,
        'kelas_kuliah' => KelasKuliahMapper::class,
        'dosen_pengajar' => DosenPengajarMapper::class,
        'komponen_evaluasi' => KomponenEvaluasiMapper::class,
        'krs' => KrsMapper::class,
        'nilai' => NilaiMapper::class,
        'nilai_komponen' => NilaiKomponenMapper::class,
        'aktivitas_mbkm' => AktivitasMbkmMapper::class,
        'anggota_mbkm' => AnggotaMbkmMapper::class,
        'bimbing_mbkm' => BimbingMbkmMapper::class,
        'tugas_akhir' => TugasAkhirMapper::class,
        'anggota_tugas_akhir' => AnggotaTugasAkhirMapper::class,
        'bimbing_tugas_akhir' => BimbingTugasAkhirMapper::class,
        'uji_tugas_akhir' => UjiTugasAkhirMapper::class,
        'prestasi' => PrestasiMapper::class,
        'transfer_kredit' => TransferKreditMapper::class,
        'konversi_mbkm' => KonversiMbkmMapper::class,
        'lulus_do' => LulusDoMapper::class,
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
     * Removes from Feeder the rows of an entity that no longer exist here.
     *
     * A push-only sync has a blind spot: a class cancelled after it was
     * reported, a KRS line dropped during revision, a student record merged
     * away — each stays at PDDIKTI exactly as it was sent, and the ratio or
     * the credit total it feeds is wrong from then on. The ledger knows what
     * left the building; this reads it back against what is still here, and
     * sends a delete for the difference.
     *
     * Soft-deleted rows count as gone: a trashed class is not one the campus
     * runs. Each delete writes its own ledger row, so a re-run finds the
     * last successful action was the delete and skips it — the same
     * idempotency as a push.
     *
     * @return array{entity: string, term: string, diperiksa: int, dihapus: int, dilewati: int, gagal: int, didukung: bool}
     */
    public function hapusYangTerhapus(string $entity, TahunAkademik $term): array
    {
        $this->pastikanAktif();

        $mapper = $this->mapper($entity);
        $actHapus = $mapper->actHapus();

        $hasil = ['entity' => $entity, 'term' => $term->kode, 'diperiksa' => 0, 'dihapus' => 0, 'dilewati' => 0, 'gagal' => 0, 'didukung' => $actHapus !== null];

        if ($actHapus === null) {
            return $hasil;
        }

        // The latest successful action per local row. Newest first, so the
        // first row seen for a (type, id) pair is the one that counts.
        $terakhir = FeederSyncLog::query()
            ->entity($entity)
            ->where('tahun_akademik_id', $term->id)
            ->where('status', FeederSyncStatus::Success->value)
            ->whereNotNull('local_type')
            ->whereNotNull('local_id')
            ->orderByDesc('id')
            ->get(['id', 'action', 'local_type', 'local_id', 'feeder_id'])
            ->unique(fn (FeederSyncLog $log): string => $log->local_type.'#'.$log->local_id)
            ->reject(fn (FeederSyncLog $log): bool => $log->action === $actHapus || blank($log->feeder_id));

        $hasil['diperiksa'] = $terakhir->count();

        foreach ($terakhir->groupBy('local_type') as $kelas => $logs) {
            if (!class_exists($kelas)) {
                continue;
            }

            /** @var Model $contoh */
            $contoh = new $kelas;
            $masihAda = $kelas::query()
                ->whereIn($contoh->getKeyName(), $logs->pluck('local_id'))
                ->pluck($contoh->getKeyName())
                ->map(fn ($id): string => (string) $id)
                ->all();

            foreach ($logs as $log) {
                if (in_array((string) $log->local_id, $masihAda, true)) {
                    $hasil['dilewati']++;

                    continue;
                }

                $kunci = $this->kunciUpdate($mapper, (string) $log->feeder_id);

                try {
                    $response = $this->client->delete($actHapus, $kunci);
                    $gagal = $response->gagal();
                    $kode = (string) $response->errorCode;
                    $pesan = $response->errorDesc;
                } catch (FeederException $e) {
                    $gagal = true;
                    $kode = (string) ($e->feederErrorCode ?? 'transport');
                    $pesan = $e->getMessage();
                }

                FeederSyncLog::create([
                    'entity' => $entity,
                    'action' => $actHapus,
                    'direction' => 'push',
                    'local_type' => $log->local_type,
                    'local_id' => $log->local_id,
                    'feeder_id' => $log->feeder_id,
                    'tahun_akademik_id' => $term->id,
                    'payload_hash' => FeederSyncLog::hashPayload($kunci),
                    'payload' => ['key' => $kunci],
                    'status' => $gagal ? FeederSyncStatus::Failed : FeederSyncStatus::Success,
                    'error_code' => $gagal ? $kode : null,
                    'error_message' => $gagal ? $pesan : null,
                    'attempts' => 1,
                    'synced_at' => $gagal ? null : now(),
                ]);

                $gagal ? $hasil['gagal']++ : $hasil['dihapus']++;
            }
        }

        return $hasil;
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
                $kode = $tipe === 'dosen'
                    ? (isset($baris['id_dosen']) ? (string) $baris['id_dosen'] : $this->tebakKode($baris))
                    : $this->tebakKode($baris);

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

            if ($tipe === 'dosen') {
                $this->petakanDosen();
            }
        }

        return $hasil;
    }

    /**
     * Gives each local lecturer the `id_dosen` PDDIKTI knows them by.
     *
     * Lecturers are registered at PDDIKTI admin, never through Feeder, so the
     * identifier can only come from reading Feeder's list back. Matched on
     * NIDN/NIDK, which is the one field both sides hold. A lecturer without
     * one — practitioners, typically — stays unmapped and is reported as
     * such by the validator rather than guessed at.
     */
    private function petakanDosen(): int
    {
        $dipetakan = 0;

        FeederRef::query()
            ->where('ref_type', 'dosen')
            ->whereNotNull('payload')
            ->each(function (FeederRef $ref) use (&$dipetakan): void {
                $nidn = $ref->payload['nidn'] ?? $ref->payload['nidk'] ?? null;

                if (blank($nidn)) {
                    return;
                }

                $dipetakan += Dosen::query()
                    ->where('nidn', (string) $nidn)
                    ->whereNull('feeder_id')
                    ->update(['feeder_id' => $ref->code]);
            });

        return $dipetakan;
    }

    /**
     * How many rows each entity has due for a term, in one query.
     *
     * The monitor shows this number for every entity on every visit. One
     * count per entity was fine at six entities and blew the screen's query
     * budget at twenty-three; a single SELECT of scalar sub-selects costs the
     * same whatever the number.
     *
     * @return array<string, int> entity => rows due
     */
    public function jumlahAntre(string $termCode): array
    {
        $kueri = DB::query();

        foreach (self::MAPPERS as $entity => $kelas) {
            $kueri->selectSub(app($kelas)->kueriJumlah($termCode), $entity);
        }

        return array_map('intval', (array) $kueri->first());
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
            $kunciAlami = $mapper->kunciAlami($model);

            $response = match (true) {
                // One upserting act, keyed on identifiers the row already holds.
                $kunciAlami !== null => $this->client->update($mapper->act(), $kunciAlami, $payload),
                $feederId === null => $this->client->insert($mapper->act(), $payload),
                default => $this->client->update(
                    str_replace('Insert', 'Update', $mapper->act()),
                    $this->kunciUpdate($mapper, $feederId),
                    $payload,
                ),
            };
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

        // A naturally keyed row ignores whatever identifier the reply carries:
        // it was never asked for one, and Feeder's answer there is noise.
        $baru = $kunciAlami !== null
            ? $mapper->feederIdBawaan($model)
            : ($response->feederId() ?? $mapper->feederIdBawaan($model));

        if ($feederId === null && $baru !== null) {
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
            DosenPengajarMapper::class => ['id_aktivitas_mengajar' => $feederId],
            KomponenEvaluasiMapper::class => ['id_komponen_evaluasi' => $feederId],
            AktivitasMbkmMapper::class, TugasAkhirMapper::class => ['id_aktivitas' => $feederId],
            AnggotaMbkmMapper::class, AnggotaTugasAkhirMapper::class => ['id_anggota' => $feederId],
            BimbingMbkmMapper::class, BimbingTugasAkhirMapper::class => ['id_bimbing_mahasiswa' => $feederId],
            UjiTugasAkhirMapper::class => ['id_uji' => $feederId],
            PrestasiMapper::class => ['id_prestasi' => $feederId],
            TransferKreditMapper::class => ['id_transfer' => $feederId],
            KonversiMbkmMapper::class => ['id_konversi_aktivitas' => $feederId],
            KurikulumMapper::class => ['id_kurikulum' => $feederId],
            MataKuliahMapper::class => ['id_matkul' => $feederId],
            MatkulKurikulumMapper::class => array_combine(
                ['id_kurikulum', 'id_matkul'],
                array_pad(explode(MatkulKurikulumMapper::PEMISAH, $feederId, 2), 2, null),
            ),
            LulusDoMapper::class => ['id_registrasi_mahasiswa' => $feederId],
            SkalaNilaiProdiMapper::class => ['id_bobot_nilai' => $feederId],
            PeriodePerkuliahanMapper::class => array_combine(
                ['id_prodi', 'id_semester'],
                array_pad(explode(PeriodePerkuliahanMapper::PEMISAH, $feederId, 2), 2, null),
            ),
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

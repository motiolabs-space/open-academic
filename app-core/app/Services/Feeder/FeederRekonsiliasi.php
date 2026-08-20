<?php

declare(strict_types=1);

namespace App\Services\Feeder;

use App\Enums\JenisSelisihFeeder;
use App\Exceptions\FeederException;
use App\Models\Akademik\TahunAkademik;
use App\Models\Feeder\FeederDiff;
use App\Services\Feeder\Contracts\FeederClientInterface;
use App\Services\Feeder\Mappers\FeederMapper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Compares what this application holds against what PDDIKTI actually holds.
 *
 * The sync ledger answers "what did we send?". That is a different question
 * from "what is over there?", and only the second one matches what the
 * ministry reads. A row can be recorded as pushed successfully and still be
 * wrong at the other end: someone edits it inside Feeder, an operator enters a
 * class by hand, a previous system left rows behind. None of that reaches a
 * push-only sync, however careful its bookkeeping.
 *
 * Three rules hold this honest:
 *
 *  1. **An entity that cannot be compared says so.** Not "cocok", not zero
 *     differences — those are claims, and a comparison that never ran has not
 *     earned them.
 *
 *  2. **A Feeder error stops the run.** A wrong act name would otherwise
 *     return no rows, and no rows looks exactly like perfect agreement while
 *     meaning the opposite.
 *
 *  3. **Only fields we send are compared.** Feeder returns far more. Reporting
 *     differences in fields this application never claimed to own would bury
 *     the ones it does.
 */
class FeederRekonsiliasi
{
    public function __construct(private readonly FeederClientInterface $client) {}

    /** Whether this entity has a comparison configured at all. */
    public function dapatDibandingkan(string $entity): bool
    {
        return filled(config("feeder.reconcile.{$entity}.get_action"));
    }

    /** @return array<int, string> entities that can be compared */
    public function entitasTerdukung(): array
    {
        return array_keys(config('feeder.reconcile', []));
    }

    /**
     * Compares one entity for one term and stores every disagreement found.
     *
     * @return array{batch: string, entity: string, term: string, lokal: int, feeder: int, cocok: int, hanya_lokal: int, hanya_feeder: int, berbeda: int, tanpa_kunci: int}
     */
    public function bandingkan(string $entity, TahunAkademik $term): array
    {
        if (!config('feeder.enabled')) {
            throw FeederException::dinonaktifkan();
        }

        $setelan = config("feeder.reconcile.{$entity}");

        if (!is_array($setelan) || blank($setelan['get_action'] ?? null)) {
            throw FeederException::tidakDapatDibandingkan($entity);
        }

        $mapper = $this->mapper($entity);
        $kunciFields = (array) ($setelan['key'] ?? []);

        $lokal = $this->indeksLokal($mapper, $term->kode, $kunciFields);
        $feeder = $this->indeksFeeder($setelan, $term->kode, $kunciFields);

        $batch = (string) Str::uuid();
        $temuan = [];

        $cocok = 0;

        foreach ($lokal['baris'] as $kunci => $satu) {
            if (!array_key_exists($kunci, $feeder)) {
                $temuan[] = $this->temuan($batch, $entity, $term, JenisSelisihFeeder::HanyaLokal, $kunci, $satu);

                continue;
            }

            $selisih = $this->bandingkanBaris($satu['payload'], $feeder[$kunci], $kunciFields);

            // Consumed, so that whatever remains in $feeder is genuinely
            // unmatched rather than merely not looked at yet.
            unset($feeder[$kunci]);

            if ($selisih === []) {
                $cocok++;

                continue;
            }

            $temuan[] = $this->temuan(
                $batch, $entity, $term, JenisSelisihFeeder::Berbeda, $kunci, $satu, $selisih,
            );
        }

        foreach ($feeder as $kunci => $baris) {
            $temuan[] = $this->temuan($batch, $entity, $term, JenisSelisihFeeder::HanyaFeeder, (string) $kunci, null);
        }

        foreach ($lokal['tanpa_kunci'] as $satu) {
            $temuan[] = $this->temuan(
                $batch, $entity, $term, JenisSelisihFeeder::TanpaKunci, '', $satu,
            );
        }

        /*
         * Findings from the previous run of this entity are cleared, not left
         * beside the new ones.
         *
         * Without this, a comparison that finds nothing writes nothing, and
         * yesterday's differences stay on the screen looking current — so the
         * one outcome an operator is working towards is the one that appears
         * to change nothing. Wrapped together with the insert: an entity left
         * half-cleared would read as fixed.
         */
        DB::transaction(function () use ($entity, $term, $temuan): void {
            FeederDiff::query()
                ->where('entity', $entity)
                ->where('term_kode', $term->kode)
                ->delete();

            foreach (array_chunk($temuan, 200) as $potongan) {
                FeederDiff::insert($potongan);
            }
        });

        return [
            'batch' => $batch,
            'entity' => $entity,
            'term' => $term->kode,
            'lokal' => count($lokal['baris']) + count($lokal['tanpa_kunci']),
            'feeder' => $cocok + count($feeder) + $this->hitung($temuan, JenisSelisihFeeder::Berbeda),
            'cocok' => $cocok,
            'hanya_lokal' => $this->hitung($temuan, JenisSelisihFeeder::HanyaLokal),
            'hanya_feeder' => $this->hitung($temuan, JenisSelisihFeeder::HanyaFeeder),
            'berbeda' => $this->hitung($temuan, JenisSelisihFeeder::Berbeda),
            'tanpa_kunci' => $this->hitung($temuan, JenisSelisihFeeder::TanpaKunci),
        ];
    }

    /* ---------------------------------------------------------------------
     | Indexing
     |-------------------------------------------------------------------- */

    /**
     * Local rows for the term, keyed by their natural key.
     *
     * @param array<int, string> $kunciFields
     * @return array{baris: array<string, array{payload: array<string, mixed>, model: Model, label: string}>, tanpa_kunci: array<int, array{payload: array<string, mixed>, model: Model, label: string}>}
     */
    private function indeksLokal(FeederMapper $mapper, string $termCode, array $kunciFields): array
    {
        $baris = [];
        $tanpaKunci = [];

        foreach ($mapper->rows($termCode) as $model) {
            $payload = $mapper->payload($model);
            $satu = ['payload' => $payload, 'model' => $model, 'label' => $mapper->label($model)];

            $kunci = $this->kunci($payload, $kunciFields);

            if ($kunci === null) {
                $tanpaKunci[] = $satu;

                continue;
            }

            /*
             * A duplicate local key is itself a finding, but not this one's to
             * report: it means two local rows would collapse into one row at
             * PDDIKTI. Kept as a mismatch against the first, so it surfaces as
             * a difference rather than vanishing.
             */
            if (!array_key_exists($kunci, $baris)) {
                $baris[$kunci] = $satu;
            } else {
                $tanpaKunci[] = $satu;
            }
        }

        return ['baris' => $baris, 'tanpa_kunci' => $tanpaKunci];
    }

    /**
     * Feeder rows for the term, keyed the same way, read back page by page.
     *
     * @param array<string, mixed> $setelan
     * @param array<int, string> $kunciFields
     * @return array<string, array<string, mixed>>
     */
    private function indeksFeeder(array $setelan, string $termCode, array $kunciFields): array
    {
        $act = (string) $setelan['get_action'];
        $filter = $this->filter((array) ($setelan['filter'] ?? []), $termCode);
        $ukuran = (int) config('feeder.reconcile_page_size', 500);

        $hasil = [];
        $offset = 0;

        // Bounded so a service that ignores offset — and answers the same page
        // forever — ends the run instead of the process.
        for ($halaman = 0; $halaman < 1000; $halaman++) {
            $response = $this->client->get($act, $filter, $ukuran, $offset);

            if ($response->gagal()) {
                throw FeederException::ditolak($act, $response->errorCode, $response->errorDesc);
            }

            $rows = $response->rows();

            foreach ($rows as $baris) {
                $kunci = $this->kunci($baris, $kunciFields);

                if ($kunci !== null) {
                    $hasil[$kunci] = $baris;
                }
            }

            if (count($rows) < $ukuran) {
                return $hasil;
            }

            $offset += $ukuran;
        }

        return $hasil;
    }

    /**
     * @param array<string, mixed> $filter
     * @return array<string, mixed>
     */
    private function filter(array $filter, string $termCode): array
    {
        return array_map(
            fn (mixed $nilai): mixed => $nilai === ':term' ? $termCode : $nilai,
            $filter,
        );
    }

    /**
     * The natural key of one row, or null when any part of it is missing.
     *
     * Null rather than a partial key: two rows that both lack a class name
     * would otherwise share a key and be declared equal to each other.
     *
     * @param array<string, mixed> $baris
     * @param array<int, string> $fields
     */
    private function kunci(array $baris, array $fields): ?string
    {
        if ($fields === []) {
            return null;
        }

        $bagian = [];

        foreach ($fields as $field) {
            $nilai = $baris[$field] ?? null;

            if (blank($nilai)) {
                return null;
            }

            $bagian[] = $this->normalkan($nilai);
        }

        return implode('|', $bagian);
    }

    /* ---------------------------------------------------------------------
     | Comparison
     |-------------------------------------------------------------------- */

    /**
     * Fields that disagree between one local payload and one Feeder row.
     *
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $feeder
     * @param array<int, string> $kunciFields
     * @return array<string, array{lokal: string|null, feeder: string|null}>
     */
    private function bandingkanBaris(array $payload, array $feeder, array $kunciFields): array
    {
        $selisih = [];

        foreach ($payload as $field => $nilaiLokal) {
            if (in_array($field, $kunciFields, true)) {
                continue;
            }

            // A field Feeder does not return is not a field Feeder disagrees
            // about — older builds simply carry fewer of them.
            if (!array_key_exists($field, $feeder)) {
                continue;
            }

            if ($this->sama($nilaiLokal, $feeder[$field])) {
                continue;
            }

            $selisih[$field] = [
                'lokal' => $this->tampil($nilaiLokal),
                'feeder' => $this->tampil($feeder[$field]),
            ];
        }

        return $selisih;
    }

    /**
     * Whether two values mean the same thing on both sides of the wire.
     *
     * Feeder answers in strings. Without this, every decimal would report as a
     * difference — 3 against "3.00", 3.5 against "3.50" — and the real
     * mismatches would be lost in thousands of them.
     */
    private function sama(mixed $lokal, mixed $feeder): bool
    {
        if (blank($lokal) && blank($feeder)) {
            return true;
        }

        if (blank($lokal) || blank($feeder)) {
            return false;
        }

        if (is_numeric($lokal) && is_numeric($feeder)) {
            return abs(((float) $lokal) - ((float) $feeder)) < 0.00001;
        }

        $tanggalLokal = $this->tanggal($lokal);
        $tanggalFeeder = $this->tanggal($feeder);

        if ($tanggalLokal !== null && $tanggalFeeder !== null) {
            return $tanggalLokal === $tanggalFeeder;
        }

        return $this->normalkan($lokal) === $this->normalkan($feeder);
    }

    /**
     * Y-m-d, for values that are unambiguously a date.
     *
     * Deliberately narrow: strtotime() would read "3" as a time of day, and
     * every SKS count in the payload would start comparing as a date.
     */
    private function tanggal(mixed $nilai): ?string
    {
        if ($nilai instanceof \DateTimeInterface) {
            return $nilai->format('Y-m-d');
        }

        if (!is_string($nilai) || !preg_match('/^\d{4}-\d{2}-\d{2}(\s|T|$)/', $nilai)) {
            return null;
        }

        return substr($nilai, 0, 10);
    }

    private function normalkan(mixed $nilai): string
    {
        if ($nilai instanceof \DateTimeInterface) {
            return $nilai->format('Y-m-d');
        }

        if (is_bool($nilai)) {
            return $nilai ? '1' : '0';
        }

        return trim(preg_replace('/\s+/', ' ', (string) $nilai) ?? '');
    }

    private function tampil(mixed $nilai): ?string
    {
        return blank($nilai) ? null : $this->normalkan($nilai);
    }

    /* ---------------------------------------------------------------------
     | Storage
     |-------------------------------------------------------------------- */

    /**
     * @param array{payload: array<string, mixed>, model: Model, label: string}|null $lokal
     * @param array<string, array{lokal: string|null, feeder: string|null}>|null $selisih
     * @return array<string, mixed>
     */
    private function temuan(
        string $batch,
        string $entity,
        TahunAkademik $term,
        JenisSelisihFeeder $jenis,
        string $kunci,
        ?array $lokal,
        ?array $selisih = null,
    ): array {
        return [
            'batch_id' => $batch,
            'entity' => $entity,
            'term_kode' => $term->kode,
            'jenis' => $jenis->value,
            'kunci' => Str::limit($kunci, 190, ''),
            'label' => $lokal === null ? null : Str::limit($lokal['label'], 190, ''),
            'local_type' => $lokal === null ? null : $lokal['model']->getMorphClass(),
            'local_id' => $lokal === null ? null : $lokal['model']->getKey(),
            'selisih' => $selisih === null ? null : json_encode($selisih, JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
        ];
    }

    /** @param array<int, array<string, mixed>> $temuan */
    private function hitung(array $temuan, JenisSelisihFeeder $jenis): int
    {
        return count(array_filter($temuan, fn (array $t): bool => $t['jenis'] === $jenis->value));
    }

    private function mapper(string $entity): FeederMapper
    {
        $kelas = FeederSyncService::MAPPERS[$entity] ?? null;

        if ($kelas === null) {
            throw FeederException::entitasTidakDikenal($entity);
        }

        return app($kelas);
    }
}

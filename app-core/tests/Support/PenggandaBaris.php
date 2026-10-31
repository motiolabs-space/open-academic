<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Multiplies rows of one table together with everything that hangs off them.
 *
 * A query budget measured on the fifty-student demo campus cannot tell a
 * screen that runs twenty queries from a screen that runs twenty queries
 * *because the demo has twenty rows*. The only instrument that can is growth:
 * count the queries, make the data bigger, count again. A screen free of N+1
 * gives the same number both times.
 *
 * Growing the data by hand per screen does not scale to a hundred screens, so
 * this walks the foreign-key graph instead. Cloning `mahasiswa` also clones
 * every row whose foreign key points at a cloned student — their study plans,
 * plan lines, grades, attendance, bills — then every row pointing at *those*,
 * remapping each foreign key to the clone. The copies join the same classes,
 * lecturers, and terms as the originals, which is exactly what makes class
 * rosters, approval queues, and advisee lists longer.
 *
 * Raw inserts, on purpose: no observers, no audit jobs, no notifications. The
 * point is rows, not events. Unique columns outside the remapped keys are
 * made distinct — `uuid` regenerated, strings prefixed, integers offset —
 * which keeps every unique index satisfied without knowing what the column
 * means. Denormalised counters (`kelas_kuliah.terisi`) are left as they were;
 * nothing here reads them for counting.
 *
 * Tests only. It knows nothing about academic rules and must never be pointed
 * at a database anyone cares about.
 */
final class PenggandaBaris
{
    private static int $generasi = 0;

    private static int $angka = 1_000_000_000;

    /** @var array<string, array<int, array{kolom: string, tabel: string}>> */
    private array $fk = [];

    /** @var array<string, array<int, array<int, string>>> */
    private array $unik = [];

    /** @var array<string, array<string, string>> */
    private array $tipe = [];

    /**
     * Clones every row of `$akar` matching `$saring` `$kali` times over.
     *
     * @param null|\Closure(Builder): mixed $saring
     * @param array<int, string> $kecuali tables never to follow into
     */
    public static function gandakan(string $akar, int $kali, ?\Closure $saring = null, array $kecuali = []): void
    {
        (new self)->jalankan($akar, $kali, $saring, $kecuali);
    }

    private function jalankan(string $akar, int $kali, ?\Closure $saring, array $kecuali): void
    {
        $tabel = collect(Schema::getTableListing(schemaQualified: false))
            ->reject(fn (string $t): bool => in_array($t, $kecuali, true) || str_starts_with($t, 'sqlite_'));

        foreach ($tabel as $t) {
            $this->fk[$t] = collect(Schema::getForeignKeys($t))
                ->filter(fn (array $k): bool => count($k['columns']) === 1)
                ->map(fn (array $k): array => ['kolom' => $k['columns'][0], 'tabel' => $k['foreign_table']])
                ->values()
                ->all();
        }

        $urutan = $this->urutan($akar, $tabel);

        // The root rows are fixed before the first copy, so copy two clones
        // the originals again rather than the first copy.
        $asal = DB::table($akar)->when($saring, fn ($q) => $saring($q))->pluck('id')->all();
        $saring = fn ($q) => $q->whereIn('id', $asal);

        DB::transaction(function () use ($akar, $kali, $saring, $urutan): void {
            for ($i = 1; $i <= $kali; $i++) {
                // Numbered across calls, not per call: a second root may reach
                // a table the first one already copied, and the distinct
                // values must not collide with that earlier copy's.
                $k = ++self::$generasi;

                /** @var array<string, array<int, int>> $peta */
                $peta = [];

                foreach ($urutan as $t) {
                    $peta[$t] = $this->salin($t, $k, $t === $akar ? $saring : null, $t === $akar, $peta);
                }
            }
        });
    }

    /**
     * Tables reachable from the root through foreign keys, parents before
     * children.
     *
     * @return array<int, string>
     */
    private function urutan(string $akar, Collection $tabel): array
    {
        $terjangkau = [$akar => true];

        do {
            $bertambah = false;

            foreach ($tabel as $t) {
                if (isset($terjangkau[$t])) {
                    continue;
                }

                foreach ($this->fk[$t] as $k) {
                    if (isset($terjangkau[$k['tabel']]) && $k['tabel'] !== $t) {
                        $terjangkau[$t] = true;
                        $bertambah = true;

                        break;
                    }
                }
            }
        } while ($bertambah);

        $sisa = array_keys($terjangkau);
        $urutan = [];

        while ($sisa !== []) {
            $maju = false;

            foreach ($sisa as $i => $t) {
                $induk = collect($this->fk[$t])
                    ->pluck('tabel')
                    ->filter(fn (string $p): bool => $p !== $t && isset($terjangkau[$p]) && !in_array($p, $urutan, true));

                if ($induk->isEmpty()) {
                    $urutan[] = $t;
                    unset($sisa[$i]);
                    $maju = true;
                }
            }

            // A cycle between two non-root tables: break it by taking the
            // first remaining table; its back-reference simply stays unmapped.
            if (!$maju) {
                $urutan[] = array_shift($sisa);
            }
        }

        return $urutan;
    }

    /**
     * @param array<string, array<int|string, int|string>> $peta
     * @return array<int|string, int|string> old id => new id
     */
    private function salin(string $t, int $k, ?\Closure $saring, bool $akar, array $peta): array
    {
        $fk = collect($this->fk[$t])->filter(fn (array $f): bool => isset($peta[$f['tabel']]) && $peta[$f['tabel']] !== []);

        if (!$akar && $fk->isEmpty()) {
            return [];
        }

        $query = DB::table($t);

        if ($akar) {
            $saring && $saring($query);
        } else {
            $query->where(function ($q) use ($fk, $peta): void {
                foreach ($fk as $f) {
                    $q->orWhereIn($f['kolom'], array_keys($peta[$f['tabel']]));
                }
            });
        }

        $kolom = $this->tipe($t);
        $punyaId = isset($kolom['id']);
        $baru = [];

        foreach ($query->get() as $baris) {
            $baris = (array) $baris;
            $diubah = [];

            foreach ($fk as $f) {
                $lama = $baris[$f['kolom']] ?? null;

                if ($lama !== null && isset($peta[$f['tabel']][$lama])) {
                    $baris[$f['kolom']] = $peta[$f['tabel']][$lama];
                    $diubah[$f['kolom']] = true;
                }
            }

            if (isset($kolom['uuid'])) {
                $baris['uuid'] = (string) Str::uuid();
            }

            $semuaFk = array_flip(array_column($this->fk[$t], 'kolom'));

            foreach ($this->unik($t) as $indeks) {
                if ($indeks === ['uuid'] || array_intersect_key(array_flip($indeks), $diubah) !== []) {
                    continue;
                }

                // Foreign keys keep pointing at real rows; only the other
                // columns of the index are made distinct.
                $bebas = array_values(array_filter(
                    $indeks,
                    fn (string $c): bool => !isset($semuaFk[$c]) && $c !== 'uuid' && $baris[$c] !== null,
                ));

                // Lower-case snake values are enum backings; prefixing them
                // turns a valid row into one no model can hydrate. A key made
                // only of those cannot be varied, so the row is not copied.
                $bebas = array_values(array_filter(
                    $bebas,
                    fn (string $c): bool => !is_string($baris[$c]) || preg_match('/^[a-z][a-z0-9_]*$/', $baris[$c]) !== 1,
                ));

                // Nothing left to vary — a one-to-one row of a parent that was
                // not cloned. Copying it would only violate the index.
                if ($bebas === []) {
                    // A key holding a NULL never collides.
                    if (array_filter($indeks, fn (string $c): bool => $baris[$c] === null) !== []) {
                        continue;
                    }

                    continue 2;
                }

                foreach ($bebas as $c) {
                    $baris[$c] = $this->bedakan($baris[$c], $kolom[$c] ?? 'varchar', $k);
                }
            }

            $lamaId = $baris['id'] ?? null;
            unset($baris['id']);

            if (!$punyaId) {
                DB::table($t)->insert($baris);
            } elseif (preg_match('/int/i', $kolom['id']) === 1) {
                $baru[$lamaId] = DB::table($t)->insertGetId($baris);
            } else {
                // A random-key table (the anonymous answer tables, ADR-043):
                // nothing assigns the key but the model, and this bypasses
                // the model on purpose, so the key is minted here.
                $baris['id'] = (string) Str::uuid();
                DB::table($t)->insert($baris);
                $baru[$lamaId] = $baris['id'];
            }
        }

        return $baru;
    }

    private function bedakan(mixed $nilai, string $tipe, int $k): mixed
    {
        // MySQL's YEAR holds 1901–2155 only; like a date it is kept, and the
        // index's other columns carry the difference.
        if (preg_match('/date|time|year/i', $tipe)) {
            return $nilai;
        }

        // Offsetting the original would collide with an earlier copy of a
        // copy; a value drawn from one counter for the whole run never does.
        if (preg_match('/int|numeric|decimal/i', $tipe)) {
            return ++self::$angka;
        }

        return 'k'.$k.$nilai;
    }

    /** @return array<string, string> column => type name */
    private function tipe(string $t): array
    {
        return $this->tipe[$t] ??= collect(Schema::getColumns($t))->pluck('type_name', 'name')->all();
    }

    /** @return array<int, array<int, string>> */
    private function unik(string $t): array
    {
        return $this->unik[$t] ??= collect(Schema::getIndexes($t))
            ->filter(fn (array $i): bool => $i['unique'] && !$i['primary'])
            ->pluck('columns')
            ->all();
    }
}

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes every foreign key that has no index starting with its columns
 * (ADR-044).
 *
 * InnoDB creates an index for each foreign key on its own; PostgreSQL and
 * SQLite never do. On PostgreSQL more than half of the foreign keys had
 * none, so every join and whereHas() through them was a sequential scan.
 * With planner statistics missing — rows a test inserts are never analysed —
 * the suite ran past ninety minutes and single queries over the demo campus
 * ran for hours. A campus on PostgreSQL pays the same scans in production,
 * only with better plans to hide them.
 *
 * The set is read from the schema rather than listed, because it is "every
 * foreign key so far" by definition. On MySQL and MariaDB it is empty, so the
 * migration skips the introspection there — hundreds of information_schema
 * reads to find nothing. Migrations after this one index their own foreign
 * keys; PortabilitasBasisDataTest fails when one does not.
 */
return new class extends Migration
{
    private const AKHIRAN = '_fk_index';

    public function up(): void
    {
        if ($this->innoDb()) {
            return;
        }

        foreach ($this->belumDiindeks() as $tabel => $daftarKolom) {
            Schema::table($tabel, function (Blueprint $table) use ($tabel, $daftarKolom): void {
                foreach ($daftarKolom as $kolom) {
                    $table->index($kolom, $this->nama($tabel, $kolom));
                }
            });
        }
    }

    public function down(): void
    {
        if ($this->innoDb()) {
            return;
        }

        foreach (Schema::getTableListing(schemaQualified: false) as $tabel) {
            $milikIni = collect(Schema::getIndexes($tabel))
                ->pluck('name')
                ->filter(fn (string $nama): bool => str_ends_with($nama, self::AKHIRAN));

            if ($milikIni->isNotEmpty()) {
                Schema::table($tabel, function (Blueprint $table) use ($milikIni): void {
                    $milikIni->each(fn (string $nama) => $table->dropIndex($nama));
                });
            }
        }
    }

    /**
     * Foreign key columns per table that no index leads with.
     *
     * @return array<string, list<list<string>>>
     */
    private function belumDiindeks(): array
    {
        $hasil = [];

        foreach (Schema::getTableListing(schemaQualified: false) as $tabel) {
            $indeks = array_map(fn (array $i): array => $i['columns'], Schema::getIndexes($tabel));

            foreach (Schema::getForeignKeys($tabel) as $fk) {
                $kolom = $fk['columns'];

                $sudah = collect($indeks)->contains(
                    fn (array $c): bool => array_slice($c, 0, count($kolom)) === $kolom,
                );

                if (!$sudah) {
                    $hasil[$tabel][] = $kolom;
                    $indeks[] = $kolom;
                }
            }
        }

        return $hasil;
    }

    /** InnoDB indexes every foreign key itself; there is nothing to add. */
    private function innoDb(): bool
    {
        return in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true);
    }

    /**
     * Laravel's default name with a suffix of our own, shortened under the
     * 63-character limit of PostgreSQL (MySQL's is 64) when it would overrun.
     *
     * @param list<string> $kolom
     */
    private function nama(string $tabel, array $kolom): string
    {
        $nama = $tabel.'_'.implode('_', $kolom).self::AKHIRAN;

        return strlen($nama) <= 63
            ? $nama
            : substr($tabel, 0, 40).'_'.substr(md5($nama), 0, 12).self::AKHIRAN;
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Anonymous answers lose their order.
 *
 * Both anonymous answer tables — the general questionnaire's and EDOM's —
 * had an auto-increment id and timestamps. Neither column names anybody,
 * but together they run in step with the participation table: the first
 * participant's answers are the lowest ids with the earliest timestamps,
 * and so on. Anyone with database access could line the two tables up and
 * read who said what. The screens and exports never showed it (ADR-039 §1);
 * the schema still allowed it, and the module's promise is that the schema
 * does not.
 *
 * Each table is rebuilt with a random UUID as its primary key and no
 * timestamp at all. Existing rows are copied over in shuffled order so that
 * nothing — not even physical row order on engines that keep it — carries
 * the old sequence. Aggregates need neither column.
 *
 * Done table by table, in place, under the original names. The indexes and
 * foreign keys get explicit short names: SQLite scopes index names to the
 * database and MySQL scopes constraint names likewise, so the old and new
 * tables cannot share them while both exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->bangunUlang(
            'kuesioner_jawaban_anonim',
            ['kuesioner_id', 'kuesioner_pertanyaan_id', 'nilai', 'teks'],
            function (Blueprint $table): void {
                $table->char('id', 36)->primary();
                $table->unsignedBigInteger('kuesioner_id');
                $table->unsignedBigInteger('kuesioner_pertanyaan_id');
                $table->unsignedTinyInteger('nilai')->nullable();
                $table->text('teks')->nullable();

                $table->foreign('kuesioner_id', 'kja_kuesioner_fk')->references('id')->on('kuesioner')->cascadeOnDelete();
                $table->foreign('kuesioner_pertanyaan_id', 'kja_pertanyaan_fk')->references('id')->on('kuesioner_pertanyaan')->cascadeOnDelete();
                $table->index(['kuesioner_id', 'kuesioner_pertanyaan_id'], 'kja_kuesioner_pertanyaan_idx');
            },
        );

        $this->bangunUlang(
            'edom_jawaban',
            ['edom_periode_id', 'kelas_kuliah_id', 'dosen_id', 'edom_pertanyaan_id', 'nilai', 'teks'],
            function (Blueprint $table): void {
                $table->char('id', 36)->primary();
                $table->unsignedBigInteger('edom_periode_id');
                $table->unsignedBigInteger('kelas_kuliah_id');
                $table->unsignedBigInteger('dosen_id');
                $table->unsignedBigInteger('edom_pertanyaan_id');
                $table->unsignedTinyInteger('nilai')->nullable();
                $table->text('teks')->nullable();

                $table->foreign('edom_periode_id', 'ej_periode_fk')->references('id')->on('edom_periode')->cascadeOnDelete();
                $table->foreign('kelas_kuliah_id', 'ej_kelas_fk')->references('id')->on('kelas_kuliah')->cascadeOnDelete();
                $table->foreign('dosen_id', 'ej_dosen_fk')->references('id')->on('dosen')->cascadeOnDelete();
                $table->foreign('edom_pertanyaan_id', 'ej_pertanyaan_fk')->references('id')->on('edom_pertanyaan')->cascadeOnDelete();
                $table->index(['edom_periode_id', 'kelas_kuliah_id', 'dosen_id'], 'ej_periode_kelas_dosen_idx');
            },
        );
    }

    /**
     * Restores sequential ids and timestamps. The order they carry is new and
     * meaningless — the original sequence is gone for good, which is the
     * point of the migration — but the shape is what the old code expects.
     */
    public function down(): void
    {
        $this->bangunUlang(
            'edom_jawaban',
            ['edom_periode_id', 'kelas_kuliah_id', 'dosen_id', 'edom_pertanyaan_id', 'nilai', 'teks'],
            function (Blueprint $table): void {
                $table->id();
                $table->foreignId('edom_periode_id')->constrained('edom_periode', indexName: 'ej_periode_fk2')->cascadeOnDelete();
                $table->foreignId('kelas_kuliah_id')->constrained('kelas_kuliah', indexName: 'ej_kelas_fk2')->cascadeOnDelete();
                $table->foreignId('dosen_id')->constrained('dosen', indexName: 'ej_dosen_fk2')->cascadeOnDelete();
                $table->foreignId('edom_pertanyaan_id')->constrained('edom_pertanyaan', indexName: 'ej_pertanyaan_fk2')->cascadeOnDelete();
                $table->unsignedTinyInteger('nilai')->nullable();
                $table->text('teks')->nullable();
                $table->timestamps();
                $table->index(['edom_periode_id', 'kelas_kuliah_id', 'dosen_id'], 'edom_jawaban_agregat_index');
            },
            berurutan: true,
        );

        $this->bangunUlang(
            'kuesioner_jawaban_anonim',
            ['kuesioner_id', 'kuesioner_pertanyaan_id', 'nilai', 'teks'],
            function (Blueprint $table): void {
                $table->id();
                $table->foreignId('kuesioner_id')->constrained('kuesioner', indexName: 'kja_kuesioner_fk2')->cascadeOnDelete();
                $table->foreignId('kuesioner_pertanyaan_id')->constrained('kuesioner_pertanyaan', indexName: 'kja_pertanyaan_fk2')->cascadeOnDelete();
                $table->unsignedTinyInteger('nilai')->nullable();
                $table->text('teks')->nullable();
                $table->timestamps();
                $table->index(['kuesioner_id', 'kuesioner_pertanyaan_id'], 'kj_anonim_pertanyaan_idx');
            },
            berurutan: true,
        );
    }

    /**
     * Rebuilds one table in place: rename the old one aside, create the new
     * shape under the original name, copy the rows across shuffled, drop the
     * old one.
     *
     * @param array<int, string> $kolom the data columns to carry over
     */
    private function bangunUlang(string $tabel, array $kolom, Closure $skema, bool $berurutan = false): void
    {
        $lama = $tabel.'_lama';

        Schema::rename($tabel, $lama);
        Schema::create($tabel, $skema);

        $baris = DB::table($lama)->get($kolom)->map(fn (object $b): array => (array) $b)->all();
        shuffle($baris);

        foreach (array_chunk($baris, 500) as $potongan) {
            if (!$berurutan) {
                $potongan = array_map(fn (array $b): array => ['id' => (string) Str::uuid()] + $b, $potongan);
            }

            DB::table($tabel)->insert($potongan);
        }

        Schema::drop($lama);
    }
};

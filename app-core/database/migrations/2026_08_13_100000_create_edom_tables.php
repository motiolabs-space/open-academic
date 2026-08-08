<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * EDOM — the questionnaire students fill in about the teaching they received.
 *
 * The whole instrument is worthless if students do not believe it is anonymous,
 * and a promise of anonymity that rests on nobody running the wrong query is not
 * anonymity. So it is enforced by the shape of the tables rather than by
 * discipline:
 *
 *   edom_partisipasi  — WHO has completed WHICH evaluation. No answers.
 *   edom_jawaban      — WHAT was answered. No student.
 *
 * **There is deliberately no key joining these two.** Not a nullable one, not an
 * indirect one. The completion record is what the enrolment gate reads and what
 * stops a second submission; the answers carry the content. No query, however
 * written, can put a student next to what they said — because the column that
 * would let it does not exist.
 *
 * Both rows are written in one transaction, so a student cannot be marked as
 * having participated without their answers landing, or the reverse.
 */
return new class extends Migration
{
    public function up(): void
    {
        /*
         * One evaluation window per term.
         *
         * Opened near the end of teaching and closed before grades are released,
         * which is the ordering that matters: an evaluation written after a
         * student sees their mark measures the mark.
         */
        Schema::create('edom_periode', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('tahun_akademik_id')->constrained('tahun_akademik')->restrictOnDelete();

            $table->string('nama');
            $table->date('mulai');
            $table->date('selesai');

            /*
             * How many responses a class needs before anybody may see its
             * results.
             *
             * A class of seven with one blunt comment identifies its author to a
             * lecturer who knows the roster. The threshold is the difference
             * between anonymous in principle and anonymous in practice, and it
             * is per period because a campus may raise it after an incident.
             */
            $table->unsignedTinyInteger('min_responden')->default(5);

            $table->boolean('is_active')->default(false)->index();

            $table->timestamps();
            $table->softDeletes();

            $table->unique('tahun_akademik_id');
        });

        /*
         * The questions, owned by the period rather than by a global bank.
         *
         * Editing a shared question would silently rewrite what last year's
         * answers meant — a 4.2 average against a question whose wording has
         * changed is a number about nothing. Same principle as the frozen
         * content on an issued letter.
         */
        Schema::create('edom_pertanyaan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('edom_periode_id')->constrained('edom_periode')->cascadeOnDelete();

            $table->string('kategori', 32)->index(); // KategoriEdom
            $table->text('teks');
            $table->string('tipe', 16)->default('skala'); // TipeJawabanEdom
            $table->unsignedSmallInteger('urutan')->default(0);

            $table->timestamps();
        });

        /*
         * Who has completed what. The gate reads this and nothing else.
         */
        Schema::create('edom_partisipasi', function (Blueprint $table) {
            $table->id();

            $table->foreignId('edom_periode_id')->constrained('edom_periode')->cascadeOnDelete();
            $table->foreignId('mahasiswa_id')->constrained('mahasiswa')->cascadeOnDelete();
            $table->foreignId('kelas_kuliah_id')->constrained('kelas_kuliah')->cascadeOnDelete();
            $table->foreignId('dosen_id')->constrained('dosen')->cascadeOnDelete();

            $table->timestamp('diisi_at');

            // One evaluation per student per lecturer per class, held by the
            // database. Two submissions would double one voice in the average.
            $table->unique(
                ['edom_periode_id', 'mahasiswa_id', 'kelas_kuliah_id', 'dosen_id'],
                'edom_partisipasi_unik',
            );

            $table->index(['mahasiswa_id', 'edom_periode_id']);
        });

        /*
         * What was answered.
         *
         * No mahasiswa_id, and no foreign key to edom_partisipasi. That absence
         * is the feature.
         *
         * Answers are not grouped into responses either: nothing needs to know
         * which answers arrived together, and a response id would be a handle
         * for correlating one person's opinions across questions — enough, in a
         * small class, to reconstruct an individual.
         */
        Schema::create('edom_jawaban', function (Blueprint $table) {
            $table->id();

            $table->foreignId('edom_periode_id')->constrained('edom_periode')->cascadeOnDelete();
            $table->foreignId('kelas_kuliah_id')->constrained('kelas_kuliah')->cascadeOnDelete();
            $table->foreignId('dosen_id')->constrained('dosen')->cascadeOnDelete();
            $table->foreignId('edom_pertanyaan_id')->constrained('edom_pertanyaan')->cascadeOnDelete();

            $table->unsignedTinyInteger('nilai')->nullable();
            $table->text('teks')->nullable();

            $table->timestamps();

            $table->index(['edom_periode_id', 'kelas_kuliah_id', 'dosen_id'], 'edom_jawaban_agregat_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edom_jawaban');
        Schema::dropIfExists('edom_partisipasi');
        Schema::dropIfExists('edom_pertanyaan');
        Schema::dropIfExists('edom_periode');
    }
};

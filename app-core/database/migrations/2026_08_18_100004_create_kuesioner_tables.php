<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kuesioner umum — the EDOM engine, generalised without losing its anonymity.
 *
 * EDOM's anonymity is **structural**: `edom_jawaban` has no column that could
 * point at a student, and `edom_partisipasi` records only that somebody
 * answered, never what. Nothing has to be remembered or enforced at runtime,
 * because there is nowhere to put the link.
 *
 * That property is kept here by refusing the obvious simplification: one answer
 * table with a nullable respondent column. With that shape, anonymity would be
 * a property of *rows* rather than of the schema — and one bug, one migration,
 * or one well-meaning "let us backfill who answered" would end it silently, for
 * data already collected under a promise.
 *
 * So there are two answer tables, and which one a questionnaire writes to is
 * fixed when it is created.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kuesioner', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->string('kode', 32)->unique();
            $table->string('nama');
            $table->text('deskripsi')->nullable();

            // SasaranKuesioner: mahasiswa | dosen | staf
            $table->string('sasaran', 16);

            /*
             * Fixed at creation and never editable afterwards.
             *
             * Flipping it later would either orphan the answers already
             * collected or, worse, retroactively attach names to answers given
             * on the understanding that none would be kept.
             */
            $table->boolean('anonim')->default(true);

            $table->date('mulai')->nullable();
            $table->date('selesai')->nullable();
            $table->boolean('is_active')->default(false);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['sasaran', 'is_active']);
        });

        Schema::create('kuesioner_pertanyaan', function (Blueprint $table) {
            $table->id();

            $table->foreignId('kuesioner_id')->constrained('kuesioner')->cascadeOnDelete();

            $table->unsignedSmallInteger('urutan')->default(0);
            $table->text('teks');

            // TipePertanyaan: skala | pilihan | teks
            $table->string('tipe', 16)->default('skala');

            // Choices for `pilihan`; ignored otherwise.
            $table->json('opsi')->nullable();

            $table->boolean('wajib')->default(true);

            $table->timestamps();

            $table->index(['kuesioner_id', 'urutan']);
        });

        /*
         * WHO answered. Never WHAT.
         *
         * Exists so a questionnaire can be gated ("you have already responded")
         * and so a response rate can be reported. Both need the fact of
         * participation; neither needs the content.
         */
        Schema::create('kuesioner_partisipasi', function (Blueprint $table) {
            $table->id();

            $table->foreignId('kuesioner_id')->constrained('kuesioner')->cascadeOnDelete();

            // Polymorphic because the target may be a student, a lecturer or a
            // member of staff, and each lives in its own table behind its own
            // guard.
            $table->string('responden_type', 64);
            $table->unsignedBigInteger('responden_id');

            $table->timestamp('diisi_at');

            $table->timestamps();

            $table->unique(
                ['kuesioner_id', 'responden_type', 'responden_id'],
                'kuesioner_partisipasi_unik',
            );
        });

        /*
         * Answers to an anonymous questionnaire.
         *
         * No respondent column exists, and that is the entire guarantee. A
         * future migration that wanted to identify these rows would have to add
         * a column and backfill it from nothing — which is impossible, which is
         * the point.
         */
        Schema::create('kuesioner_jawaban_anonim', function (Blueprint $table) {
            $table->id();

            $table->foreignId('kuesioner_id')->constrained('kuesioner')->cascadeOnDelete();
            $table->foreignId('kuesioner_pertanyaan_id')->constrained('kuesioner_pertanyaan')->cascadeOnDelete();

            $table->unsignedTinyInteger('nilai')->nullable();
            $table->text('teks')->nullable();

            $table->timestamps();

            /*
             * Nama indeks ditulis eksplisit, bukan dibiarkan otomatis.
             *
             * Nama bawaan Laravel untuk pasangan kolom ini pada tabel anonim
             * jadi 67 karakter — melewati batas 64 karakter MySQL, dan
             * migrasinya gagal total di sana. SQLite tidak punya batas itu,
             * jadi seluruh suite tetap hijau sementara produksi tidak dapat
             * dipasang sama sekali.
             */
            $table->index(['kuesioner_id', 'kuesioner_pertanyaan_id'], 'kj_anonim_pertanyaan_idx');
        });

        /*
         * Answers to an identified questionnaire.
         *
         * A separate table rather than a nullable column on the one above, so
         * that "is this questionnaire anonymous" is answered by which table the
         * rows are in — a fact no code path can accidentally change.
         */
        Schema::create('kuesioner_jawaban', function (Blueprint $table) {
            $table->id();

            $table->foreignId('kuesioner_id')->constrained('kuesioner')->cascadeOnDelete();
            $table->foreignId('kuesioner_pertanyaan_id')->constrained('kuesioner_pertanyaan')->cascadeOnDelete();

            $table->string('responden_type', 64);
            $table->unsignedBigInteger('responden_id');

            $table->unsignedTinyInteger('nilai')->nullable();
            $table->text('teks')->nullable();

            $table->timestamps();

            $table->index(['kuesioner_id', 'kuesioner_pertanyaan_id'], 'kj_pertanyaan_idx');
            $table->index(['responden_type', 'responden_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kuesioner_jawaban');
        Schema::dropIfExists('kuesioner_jawaban_anonim');
        Schema::dropIfExists('kuesioner_partisipasi');
        Schema::dropIfExists('kuesioner_pertanyaan');
        Schema::dropIfExists('kuesioner');
    }
};

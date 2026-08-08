<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RPS — the teaching plan, and the spine that makes mastery measurable.
 *
 * Without it, "how well does this student understand the material" can only be
 * answered with the grade, which is the question restated rather than answered.
 * With it, a grade component can be tied to a specific learning outcome, and the
 * answer becomes "weak on CPL-03 across three courses" — something a lecturer
 * and an advisor can act on.
 *
 * The plan sits on the **course within a term**, not on the class. Parallel
 * classes of one course teach the same plan; if they did not, the two cohorts
 * would receive different degrees under one transcript. What differs per class
 * is what was actually delivered, and that is the journal — see
 * `pertemuan_kelas`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rps', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('mata_kuliah_id')->constrained('mata_kuliah')->cascadeOnDelete();
            $table->foreignId('tahun_akademik_id')->constrained('tahun_akademik')->restrictOnDelete();

            $table->unsignedSmallInteger('versi')->default(1);
            $table->string('status', 16)->default('draft'); // StatusRps

            $table->text('deskripsi')->nullable();
            $table->text('pustaka')->nullable();

            $table->foreignId('disusun_by_dosen_id')->nullable()->constrained('dosen')->nullOnDelete();
            $table->foreignId('disahkan_by_dosen_id')->nullable()->constrained('dosen')->nullOnDelete();
            $table->timestamp('disahkan_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            /*
             * At most one published plan per course per term.
             *
             * Nullable-unique holding the composite while published, NULL
             * otherwise — the portable "one active X" pattern used by
             * tugas_akhir.mahasiswa_aktif_id and jabatan_fungsional_dosen.
             * A draft revision can be written alongside the one in force.
             */
            $table->string('kunci_aktif', 64)->nullable()->unique();

            $table->index(['mata_kuliah_id', 'tahun_akademik_id']);
        });

        /*
         * Which programme outcomes this course is answerable for.
         *
         * A course carries a handful, not all of them. Claiming every CPL is the
         * same as claiming none: the mastery figure becomes an average over
         * everything and stops pointing anywhere.
         */
        Schema::create('rps_cpl', function (Blueprint $table) {
            $table->id();

            $table->foreignId('rps_id')->constrained('rps')->cascadeOnDelete();
            $table->foreignId('prodi_cpl_id')->constrained('prodi_cpl')->cascadeOnDelete();

            // CPMK — the course-level restatement of that outcome.
            $table->text('rumusan')->nullable();

            $table->timestamps();

            $table->unique(['rps_id', 'prodi_cpl_id'], 'rps_cpl_unik');
        });

        /*
         * The weekly plan: sixteen rows, one per meeting.
         *
         * `bobot` is the share of assessment attributed to this meeting. It is
         * planning information — the figures that actually decide a grade live
         * in `komponen_nilai`, and the two are deliberately not wired together.
         * A plan that silently rewrote a mark would make the plan dangerous to
         * edit.
         */
        Schema::create('rps_pertemuan', function (Blueprint $table) {
            $table->id();

            $table->foreignId('rps_id')->constrained('rps')->cascadeOnDelete();

            $table->unsignedTinyInteger('pertemuan_ke');

            $table->text('kemampuan_akhir');           // Sub-CPMK
            $table->text('bahan_kajian')->nullable();
            $table->string('metode', 64)->nullable();  // ceramah, diskusi, studi kasus, praktikum
            $table->text('indikator')->nullable();
            $table->unsignedTinyInteger('bobot')->default(0);

            $table->timestamps();

            $table->unique(['rps_id', 'pertemuan_ke'], 'rps_pertemuan_unik');
        });

        /*
         * Which outcome each grade component measures, and how much of it.
         *
         * A pivot rather than a column on `komponen_nilai`, because one midterm
         * routinely measures two or three outcomes. Forcing a single foreign key
         * would make somebody choose one and quietly discard the rest — and the
         * discarded ones are exactly the outcomes that then appear unmeasured.
         *
         * `porsi` is the percentage of this component attributable to that
         * outcome, so a 100-mark exam can be 60% CPL-02 and 40% CPL-05.
         */
        Schema::create('komponen_nilai_cpl', function (Blueprint $table) {
            $table->id();

            $table->foreignId('komponen_nilai_id')->constrained('komponen_nilai')->cascadeOnDelete();
            $table->foreignId('prodi_cpl_id')->constrained('prodi_cpl')->cascadeOnDelete();

            $table->unsignedTinyInteger('porsi')->default(100); // percent of this component

            $table->timestamps();

            $table->unique(['komponen_nilai_id', 'prodi_cpl_id'], 'komponen_nilai_cpl_unik');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('komponen_nilai_cpl');
        Schema::dropIfExists('rps_pertemuan');
        Schema::dropIfExists('rps_cpl');
        Schema::dropIfExists('rps');
    }
};

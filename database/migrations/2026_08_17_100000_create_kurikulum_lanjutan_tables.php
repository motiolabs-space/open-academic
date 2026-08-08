<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Three things a curriculum needs once it has been replaced at least once.
 *
 * All three exist because a curriculum is not a static list: it is superseded,
 * it branches into tracks, and in vocational programmes it is handed to the
 * student rather than chosen by them.
 */
return new class extends Migration
{
    public function up(): void
    {
        /*
         * Course equivalence — "passing A counts as having passed B".
         *
         * **Directed, and that direction is load-bearing.** A 2018 course being
         * recognised as its 2026 replacement does not mean the reverse: the new
         * one may cover more ground, and accepting it backwards would let a
         * current student skip a prerequisite the old syllabus never taught.
         *
         * Campuses that genuinely mean both ways record two rows, deliberately,
         * rather than getting it by default from a flag nobody reads.
         */
        Schema::create('mata_kuliah_padanan', function (Blueprint $table) {
            $table->id();

            // The course the student actually passed.
            $table->foreignId('mata_kuliah_id')->constrained('mata_kuliah')->cascadeOnDelete();

            // The course it is recognised as.
            $table->foreignId('diakui_sebagai_id')->constrained('mata_kuliah')->cascadeOnDelete();

            $table->string('catatan', 500)->nullable();
            $table->foreignId('ditetapkan_by_staff_id')->nullable()->constrained('staff')->nullOnDelete();

            $table->timestamps();

            $table->unique(['mata_kuliah_id', 'diakui_sebagai_id'], 'mata_kuliah_padanan_unik');
            $table->index('diakui_sebagai_id');
        });

        /*
         * Concentrations — one programme, several compulsory tracks.
         *
         * Hangs off the curriculum rather than the programme: a track exists in
         * the curriculum that defines it, and a curriculum revision routinely
         * renames or merges tracks. Attaching to the programme would make last
         * year's students members of this year's tracks.
         */
        Schema::create('konsentrasi', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('kurikulum_id')->constrained('kurikulum')->cascadeOnDelete();

            $table->string('kode', 16);
            $table->string('nama');
            $table->text('deskripsi')->nullable();

            // Credits that must come from this track's own courses.
            $table->unsignedSmallInteger('sks_wajib')->default(0);

            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['kurikulum_id', 'kode']);
        });

        Schema::table('kurikulum_mata_kuliah', function (Blueprint $table) {
            /*
             * Null means the course belongs to every track.
             *
             * The common case by a wide margin — most of a degree is shared —
             * so null is the default rather than something to be filled in.
             */
            $table->foreignId('konsentrasi_id')->nullable()->after('mata_kuliah_id')
                ->constrained('konsentrasi')->nullOnDelete();
        });

        Schema::table('mahasiswa', function (Blueprint $table) {
            // Chosen partway through the degree, so nullable for the years
            // before a student picks one.
            $table->foreignId('konsentrasi_id')->nullable()->after('kurikulum_id')
                ->constrained('konsentrasi')->nullOnDelete();
        });

        /*
         * Packaged semesters — the study plan is issued, not chosen.
         *
         * Normal in vocational and diploma programmes, where a cohort moves
         * through a fixed sequence together. The rules a chosen plan must
         * satisfy still apply; what changes is who does the choosing.
         */
        Schema::create('paket_kuliah', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('kurikulum_id')->constrained('kurikulum')->cascadeOnDelete();
            $table->foreignId('konsentrasi_id')->nullable()->constrained('konsentrasi')->nullOnDelete();

            $table->unsignedTinyInteger('semester_ke');
            $table->string('nama');

            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            // One package per (curriculum, track, semester). Two would leave
            // nobody able to say which one a cohort receives.
            $table->unique(['kurikulum_id', 'konsentrasi_id', 'semester_ke'], 'paket_kuliah_unik');
        });

        Schema::create('paket_kuliah_detail', function (Blueprint $table) {
            $table->id();

            $table->foreignId('paket_kuliah_id')->constrained('paket_kuliah')->cascadeOnDelete();
            $table->foreignId('mata_kuliah_id')->constrained('mata_kuliah')->restrictOnDelete();

            $table->timestamps();

            $table->unique(['paket_kuliah_id', 'mata_kuliah_id'], 'paket_kuliah_detail_unik');
        });

        Schema::table('prodi', function (Blueprint $table) {
            // "pilih" | "paket" — who assembles the study plan.
            $table->string('mode_krs', 8)->default('pilih')->after('kaprodi_dosen_id');
        });
    }

    public function down(): void
    {
        Schema::table('prodi', fn (Blueprint $table) => $table->dropColumn('mode_krs'));

        Schema::dropIfExists('paket_kuliah_detail');
        Schema::dropIfExists('paket_kuliah');

        Schema::table('mahasiswa', function (Blueprint $table) {
            $table->dropForeign(['konsentrasi_id']);
            $table->dropColumn('konsentrasi_id');
        });

        Schema::table('kurikulum_mata_kuliah', function (Blueprint $table) {
            $table->dropForeign(['konsentrasi_id']);
            $table->dropColumn('konsentrasi_id');
        });

        Schema::dropIfExists('konsentrasi');
        Schema::dropIfExists('mata_kuliah_padanan');
    }
};

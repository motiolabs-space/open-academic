<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Outcome-based curriculum below the programme outcome: CPL → CPMK → sub-CPMK.
 *
 * Until now a course said which CPL it answered for (rps_cpl) and a grade
 * component could be tied straight to a CPL (komponen_nilai_cpl). What was
 * missing is the layer an accreditation visit actually reads: what this course
 * promises (CPMK), broken into what each part of the course delivers
 * (sub-CPMK), each weighted, and each measured by a named component.
 *
 * **Versioned per curriculum, and frozen once in force.** The set belongs to a
 * course *within a curriculum version* — the same course in the 2022 and 2026
 * curricula can promise different things. Within one curriculum it is revised
 * by publishing a new version; the one in force is never edited, because a
 * mark already recorded against CPMK-2 must still mean CPMK-2 afterwards
 * (ADR-038).
 *
 * Weights are integers per hundred, like every weight in this schema.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cpmk_versi', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('kurikulum_id')->constrained('kurikulum')->restrictOnDelete();
            $table->foreignId('mata_kuliah_id')->constrained('mata_kuliah')->restrictOnDelete();

            $table->unsignedSmallInteger('versi')->default(1);
            $table->string('status', 16)->default('draft'); // StatusCpmk

            $table->foreignId('disusun_by_staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->foreignId('disusun_by_dosen_id')->nullable()->constrained('dosen')->nullOnDelete();

            // Who published it, by name: a staff member or a lecturer, and the
            // name survives either account being removed.
            $table->string('diterbitkan_oleh')->nullable();
            $table->timestamp('diterbitkan_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // One version in force per course per curriculum — the nullable
            // unique "kurikulum:mata_kuliah" pattern also used by rps.
            $table->string('kunci_aktif', 64)->nullable()->unique();

            $table->index(['kurikulum_id', 'mata_kuliah_id'], 'cpmk_versi_kurikulum_mk_index');
        });

        Schema::create('cpmk', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('cpmk_versi_id')->constrained('cpmk_versi')->cascadeOnDelete();

            $table->string('kode', 16);
            $table->text('rumusan');
            $table->unsignedSmallInteger('urutan')->default(0);

            $table->timestamps();

            $table->unique(['cpmk_versi_id', 'kode'], 'cpmk_versi_kode_unik');
        });

        Schema::create('sub_cpmk', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('cpmk_id')->constrained('cpmk')->cascadeOnDelete();

            $table->string('kode', 24);
            $table->text('rumusan');

            // Share of the parent CPMK, per hundred. Siblings total 100.
            $table->unsignedSmallInteger('bobot')->default(0);
            $table->unsignedSmallInteger('urutan')->default(0);

            $table->timestamps();

            $table->unique(['cpmk_id', 'kode'], 'sub_cpmk_kode_unik');
        });

        /*
         * Which CPL a CPMK contributes to, and how much.
         *
         * `bobot` is this CPMK's share of the course's contribution to that
         * CPL: for each CPL the course is charged with, the CPMK mapped to it
         * total 100. One CPMK may feed two CPL.
         */
        Schema::create('cpmk_cpl', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cpmk_id')->constrained('cpmk')->cascadeOnDelete();
            $table->foreignId('prodi_cpl_id')->constrained('prodi_cpl')->restrictOnDelete();

            $table->unsignedSmallInteger('bobot')->default(100);

            $table->timestamps();

            $table->unique(['cpmk_id', 'prodi_cpl_id'], 'cpmk_cpl_unik');
        });

        /*
         * What each grade component measures.
         *
         * The target is a sub-CPMK when its CPMK has any, otherwise the CPMK
         * itself — never both levels for one CPMK, so a mark is never counted
         * twice on its way up. `cpmk_id` is stored either way so the roll-up
         * reads one column. `porsi` is the share of the component, per hundred.
         *
         * restrictOnDelete on the targets: only versions in force can be mapped,
         * and those are never deleted.
         */
        Schema::create('komponen_nilai_cpmk', function (Blueprint $table) {
            $table->id();

            $table->foreignId('komponen_nilai_id')->constrained('komponen_nilai')->cascadeOnDelete();
            $table->foreignId('cpmk_id')->constrained('cpmk')->restrictOnDelete();
            $table->foreignId('sub_cpmk_id')->nullable()->constrained('sub_cpmk')->restrictOnDelete();

            $table->unsignedSmallInteger('porsi')->default(100);

            $table->timestamps();

            $table->unique(['komponen_nilai_id', 'cpmk_id', 'sub_cpmk_id'], 'komponen_nilai_cpmk_unik');
        });

        /*
         * Attainment per student per CPMK, written when the class is finalised.
         *
         * Derived data, stored rather than recomputed on every screen: the
         * figure an accreditation table quotes must be the figure that existed
         * when the grade was locked, and it changes only through the audited
         * correction path. `cakupan` is the share of the CPMK's sub-CPMK weight
         * that anything measured — 100 when every part was assessed.
         */
        Schema::create('capaian_cpmk_mahasiswa', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('krs_detail_id')->constrained('krs_detail')->restrictOnDelete();
            $table->foreignId('mahasiswa_id')->constrained('mahasiswa')->restrictOnDelete();
            $table->foreignId('kelas_kuliah_id')->constrained('kelas_kuliah')->restrictOnDelete();
            $table->foreignId('cpmk_id')->constrained('cpmk')->restrictOnDelete();

            $table->decimal('nilai', 5, 2);
            $table->unsignedTinyInteger('cakupan')->default(100);

            $table->timestamps();

            $table->unique(['krs_detail_id', 'cpmk_id'], 'capaian_cpmk_mhs_unik');
        });

        /*
         * Attainment per student per CPL *within one course*.
         *
         * The cross-course figure is a credit-weighted mean of these rows —
         * SUM(nilai × sks) / SUM(sks) — read in one aggregate, never rebuilt
         * from marks. `sks` is the line's credits, copied so the roll-up needs
         * no join.
         */
        Schema::create('capaian_cpl_mahasiswa', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('krs_detail_id')->constrained('krs_detail')->restrictOnDelete();
            $table->foreignId('mahasiswa_id')->constrained('mahasiswa')->restrictOnDelete();
            $table->foreignId('kelas_kuliah_id')->constrained('kelas_kuliah')->restrictOnDelete();
            $table->foreignId('prodi_cpl_id')->constrained('prodi_cpl')->restrictOnDelete();

            $table->decimal('nilai', 5, 2);
            $table->unsignedTinyInteger('sks')->default(0);

            $table->timestamps();

            $table->unique(['krs_detail_id', 'prodi_cpl_id'], 'capaian_cpl_mhs_unik');
        });

        /*
         * The plan reads the same outcomes it is assessed against.
         *
         * An RPS used to carry its own CPMK text per CPL (rps_cpl.rumusan) and
         * its own sub-CPMK text per week (kemampuan_akhir). Both stay — for
         * plans written before this — but a plan attached to a version shows
         * that version's rows, and a week names the sub-CPMK it teaches.
         */
        Schema::table('rps', function (Blueprint $table) {
            $table->foreignId('cpmk_versi_id')->nullable()->after('tahun_akademik_id')
                ->constrained('cpmk_versi')->restrictOnDelete();
        });

        Schema::table('rps_pertemuan', function (Blueprint $table) {
            $table->foreignId('sub_cpmk_id')->nullable()->after('pertemuan_ke')
                ->constrained('sub_cpmk')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('rps_pertemuan', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sub_cpmk_id');
        });

        Schema::table('rps', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cpmk_versi_id');
        });

        Schema::dropIfExists('capaian_cpl_mahasiswa');
        Schema::dropIfExists('capaian_cpmk_mahasiswa');
        Schema::dropIfExists('komponen_nilai_cpmk');
        Schema::dropIfExists('cpmk_cpl');
        Schema::dropIfExists('sub_cpmk');
        Schema::dropIfExists('cpmk');
        Schema::dropIfExists('cpmk_versi');
    }
};

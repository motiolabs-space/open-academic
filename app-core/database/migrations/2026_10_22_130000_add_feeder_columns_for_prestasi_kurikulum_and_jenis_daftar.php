<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Three more groups of PDDIKTI entities get their Feeder identifiers, and the
 * student's enrolment record learns how they came in.
 *
 * Achievements, recognised transfer credits and MBKM credit conversions are
 * pushed per row; the curriculum, its courses and the course-curriculum link
 * are the reference data every class and KRS payload points at — until now
 * assumed to already exist at Feeder, which is true nowhere a campus starts
 * from scratch.
 *
 * `mahasiswa.jenis_daftar` is PDDIKTI's "jenis pendaftaran": new entrant,
 * transfer, recognition of prior learning, fast track, non-degree. Transfer
 * and RPL must carry the credits recognised and where they came from, so the
 * three columns beside it exist for those and stay null for everyone else.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['poin_mahasiswa', 'konversi_kredit', 'kurikulum', 'mata_kuliah', 'kurikulum_mata_kuliah'] as $tabel) {
            Schema::table($tabel, function (Blueprint $table) {
                $table->string('feeder_id', 64)->nullable()->index();
                $table->timestamp('feeder_synced_at')->nullable();
            });
        }

        // The KRS line already carries the KRS entity's id; the conversion it
        // represents is a second PDDIKTI record with an id of its own.
        Schema::table('krs_detail', function (Blueprint $table) {
            $table->string('feeder_konversi_id', 64)->nullable()->after('feeder_id');
        });

        Schema::table('mahasiswa', function (Blueprint $table) {
            $table->string('jenis_daftar', 16)->nullable()->after('jalur_masuk'); // JenisDaftar
            $table->string('pt_asal')->nullable()->after('jenis_daftar');
            $table->string('prodi_asal')->nullable()->after('pt_asal');
            $table->unsignedSmallInteger('sks_diakui')->nullable()->after('prodi_asal');
        });
    }

    public function down(): void
    {
        Schema::table('mahasiswa', function (Blueprint $table) {
            $table->dropColumn(['jenis_daftar', 'pt_asal', 'prodi_asal', 'sks_diakui']);
        });

        Schema::table('krs_detail', function (Blueprint $table) {
            $table->dropColumn('feeder_konversi_id');
        });

        foreach (['kurikulum_mata_kuliah', 'mata_kuliah', 'kurikulum', 'konversi_kredit', 'poin_mahasiswa'] as $tabel) {
            Schema::table($tabel, function (Blueprint $table) {
                $table->dropIndex(['feeder_id']);
                $table->dropColumn(['feeder_id', 'feeder_synced_at']);
            });
        }
    }
};

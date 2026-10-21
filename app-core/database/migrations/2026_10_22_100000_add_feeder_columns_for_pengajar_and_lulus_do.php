<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two Feeder entities that always had local tables but were never pushed.
 *
 * `kelas_dosen` gets the same pair of columns every other pushed entity has:
 * Feeder's identifier for the row and when it last went. Without them a
 * lecturer assignment cannot be updated in place and every re-run would
 * insert a duplicate.
 *
 * `mahasiswa.feeder_keluar_id` marks that the student's exit (lulus / DO /
 * mengundurkan diri) has reached PDDIKTI. Feeder keys that record on
 * `id_registrasi_mahasiswa`, which the student already carries, so the column
 * holds that same value once the push succeeds — a flag that doubles as the
 * update key, rather than a second identifier to keep in step.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kelas_dosen', function (Blueprint $table) {
            $table->string('feeder_id', 64)->nullable()->index()->after('praktisi_instansi');
            $table->timestamp('feeder_synced_at')->nullable()->after('feeder_id');
        });

        Schema::table('mahasiswa', function (Blueprint $table) {
            $table->string('feeder_keluar_id', 64)->nullable()->index()->after('feeder_registrasi_id');
        });
    }

    public function down(): void
    {
        Schema::table('mahasiswa', function (Blueprint $table) {
            $table->dropIndex(['feeder_keluar_id']);
            $table->dropColumn('feeder_keluar_id');
        });

        Schema::table('kelas_dosen', function (Blueprint $table) {
            $table->dropIndex(['feeder_id']);
            $table->dropColumn(['feeder_id', 'feeder_synced_at']);
        });
    }
};

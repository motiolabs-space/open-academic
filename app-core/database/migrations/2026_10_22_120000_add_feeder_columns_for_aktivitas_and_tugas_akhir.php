<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Student activities become PDDIKTI entities.
 *
 * PDDIKTI's "Aktivitas Mahasiswa" is one record with three satellites: the
 * member (the student), the supervisors, and the examiners. Locally the same
 * facts live in two places — `aktivitas_mahasiswa` for MBKM programmes and
 * `tugas_akhir` for the final project — so each carries the activity id and
 * the member id Feeder hands back, and the supervisor / examiner rows carry
 * their own. An MBKM activity has at most one supervisor, recorded on the
 * activity itself; hence `feeder_bimbing_id` there and nowhere else.
 *
 * This is also the route by which a lecturer's supervising and examining
 * reach SISTER: it reads them from PDDIKTI, not from the campus.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aktivitas_mahasiswa', function (Blueprint $table) {
            $table->string('feeder_id', 64)->nullable()->index()->after('verified_at');
            $table->timestamp('feeder_synced_at')->nullable()->after('feeder_id');
            $table->string('feeder_anggota_id', 64)->nullable()->after('feeder_synced_at');
            $table->string('feeder_bimbing_id', 64)->nullable()->after('feeder_anggota_id');
        });

        Schema::table('tugas_akhir', function (Blueprint $table) {
            $table->string('feeder_id', 64)->nullable()->index()->after('status');
            $table->timestamp('feeder_synced_at')->nullable()->after('feeder_id');
            $table->string('feeder_anggota_id', 64)->nullable()->after('feeder_synced_at');
        });

        Schema::table('tugas_akhir_pembimbing', function (Blueprint $table) {
            $table->string('feeder_id', 64)->nullable()->index()->after('ditetapkan_pada');
            $table->timestamp('feeder_synced_at')->nullable()->after('feeder_id');
        });

        Schema::table('tugas_akhir_penguji', function (Blueprint $table) {
            $table->string('feeder_id', 64)->nullable()->index()->after('catatan');
            $table->timestamp('feeder_synced_at')->nullable()->after('feeder_id');
        });
    }

    public function down(): void
    {
        Schema::table('tugas_akhir_penguji', function (Blueprint $table) {
            $table->dropIndex(['feeder_id']);
            $table->dropColumn(['feeder_id', 'feeder_synced_at']);
        });

        Schema::table('tugas_akhir_pembimbing', function (Blueprint $table) {
            $table->dropIndex(['feeder_id']);
            $table->dropColumn(['feeder_id', 'feeder_synced_at']);
        });

        Schema::table('tugas_akhir', function (Blueprint $table) {
            $table->dropIndex(['feeder_id']);
            $table->dropColumn(['feeder_id', 'feeder_synced_at', 'feeder_anggota_id']);
        });

        Schema::table('aktivitas_mahasiswa', function (Blueprint $table) {
            $table->dropIndex(['feeder_id']);
            $table->dropColumn(['feeder_id', 'feeder_synced_at', 'feeder_anggota_id', 'feeder_bimbing_id']);
        });
    }
};

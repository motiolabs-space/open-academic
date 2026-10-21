<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The class evaluation plan becomes a PDDIKTI entity.
 *
 * Komponen Evaluasi Kelas has been a mandatory report since the 2024 odd
 * term, and the per-component scores go with it. Both already exist here —
 * `komponen_nilai` and `nilai_komponen` are what the grade sheet is built on
 * — but two things were missing for Feeder to accept them: the *kind* of each
 * component (PDDIKTI's jenis evaluasi, which also decides whether a case
 * method class counts for IKU 7), and the identifier Feeder assigns so a
 * re-run updates instead of duplicating.
 *
 * `jenis_evaluasi` is nullable: existing rows have no kind yet, and the
 * validator reports them rather than a migration guessing one from the name.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('komponen_nilai', function (Blueprint $table) {
            $table->string('jenis_evaluasi', 16)->nullable()->after('bobot'); // JenisEvaluasi
            $table->string('feeder_id', 64)->nullable()->index()->after('urutan');
            $table->timestamp('feeder_synced_at')->nullable()->after('feeder_id');
        });

        Schema::table('nilai_komponen', function (Blueprint $table) {
            $table->string('feeder_id', 64)->nullable()->index()->after('nilai');
            $table->timestamp('feeder_synced_at')->nullable()->after('feeder_id');
        });
    }

    public function down(): void
    {
        Schema::table('nilai_komponen', function (Blueprint $table) {
            $table->dropIndex(['feeder_id']);
            $table->dropColumn(['feeder_id', 'feeder_synced_at']);
        });

        Schema::table('komponen_nilai', function (Blueprint $table) {
            $table->dropIndex(['feeder_id']);
            $table->dropColumn(['jenis_evaluasi', 'feeder_id', 'feeder_synced_at']);
        });
    }
};

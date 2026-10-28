<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `riwayat_dosen_wali` is the history of who advised a student, one row per
 * change of `mahasiswa.dosen_wali_id`.
 *
 * The column on `mahasiswa` stays the single answer to "who decides this
 * student's KRS today"; this table answers "who decided it last year, who
 * moved it, and why". An advisor change re-routes study plans already waiting
 * for a decision, so the row also keeps how many were moved.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('riwayat_dosen_wali', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('mahasiswa_id')->constrained('mahasiswa')->restrictOnDelete();

            // Null on the first assignment.
            $table->foreignId('dosen_lama_id')->nullable()->constrained('dosen')->restrictOnDelete();
            $table->foreignId('dosen_baru_id')->constrained('dosen')->restrictOnDelete();

            $table->string('cara', 16); // CaraPenetapanWali
            $table->string('alasan', 500)->nullable();
            $table->unsignedSmallInteger('krs_dialihkan')->default(0);

            $table->foreignId('ditetapkan_by_staff_id')->nullable()->constrained('staff')->nullOnDelete();

            $table->timestamps();

            $table->index(['mahasiswa_id', 'created_at'], 'riwayat_dosen_wali_mahasiswa_waktu_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayat_dosen_wali');
    }
};

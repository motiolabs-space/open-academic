<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A document the assessor asks the student for while a credit conversion is
 * pending, and the one the student sends back (ADR-040 §4).
 *
 * Kept apart from `berkas_path`, which is what staff attached when proposing:
 * the student's upload never replaces the evidence the proposal was made on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('konversi_kredit', function (Blueprint $table) {
            $table->string('dokumen_diminta', 500)->nullable()->after('berkas_path');
            $table->timestamp('dokumen_diminta_at')->nullable()->after('dokumen_diminta');
            $table->foreignId('dokumen_diminta_by_staff_id')->nullable()->after('dokumen_diminta_at')
                ->constrained('staff')->nullOnDelete();
            $table->string('berkas_mahasiswa_path')->nullable()->after('dokumen_diminta_by_staff_id');
            $table->timestamp('berkas_mahasiswa_at')->nullable()->after('berkas_mahasiswa_path');
        });
    }

    public function down(): void
    {
        Schema::table('konversi_kredit', function (Blueprint $table) {
            $table->dropConstrainedForeignId('dokumen_diminta_by_staff_id');
            $table->dropColumn(['dokumen_diminta', 'dokumen_diminta_at', 'berkas_mahasiswa_path', 'berkas_mahasiswa_at']);
        });
    }
};

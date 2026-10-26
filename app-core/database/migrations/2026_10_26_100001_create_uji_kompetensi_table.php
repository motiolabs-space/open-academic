<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per sitting of the competency examination, failed sittings included.
 *
 * Results are events, not a field on the student: a graduate who passes on the
 * third attempt has three rows, and the two that did not pass are the
 * programme's own evidence of how its graduates fare.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('uji_kompetensi', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('mahasiswa_id')->constrained('mahasiswa')->restrictOnDelete();

            // The term the sitting falls in. For a passing result this is the
            // term PDDIKTI records the student as having left in.
            $table->foreignId('tahun_akademik_id')->constrained('tahun_akademik')->restrictOnDelete();

            $table->date('tanggal_ujian');
            $table->string('hasil', 16); // HasilUjiKompetensi
            $table->string('nomor_sertifikat', 64)->nullable();
            $table->string('catatan', 500)->nullable();

            $table->foreignId('dicatat_by_staff_id')->nullable()->constrained('staff')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['mahasiswa_id', 'hasil']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('uji_kompetensi');
    }
};

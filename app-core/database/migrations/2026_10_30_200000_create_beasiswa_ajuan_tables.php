<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Scholarship applications (ADR-040 §2).
 *
 * `beasiswa_periode` is a call for applications under one scheme: when it is
 * open, what it asks for, and the term an award would start in.
 * `beasiswa_ajuan` is one student's application to it. Neither moves money —
 * an approved application points at the `beasiswa_penerima` row that
 * BeasiswaService::tetapkan() created, which is where the discount lives.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beasiswa_periode', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('beasiswa_id')->constrained('beasiswa')->restrictOnDelete();
            $table->foreignId('tahun_akademik_id')->constrained('tahun_akademik')->restrictOnDelete();
            $table->string('nama');

            $table->date('dibuka_mulai');
            $table->date('dibuka_sampai');

            // What the student reads before applying, the one requirement the
            // application checks for itself, and the documents to upload.
            $table->text('syarat')->nullable();
            $table->decimal('ipk_minimum', 3, 2)->nullable();
            $table->json('berkas_diminta')->nullable();

            $table->foreignId('dibuat_by_staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->timestamp('ditutup_at')->nullable();
            $table->foreignId('ditutup_by_staff_id')->nullable()->constrained('staff')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['dibuka_mulai', 'dibuka_sampai'], 'beasiswa_periode_dibuka_index');
        });

        Schema::create('beasiswa_ajuan', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('beasiswa_periode_id')->constrained('beasiswa_periode')->restrictOnDelete();
            $table->foreignId('mahasiswa_id')->constrained('mahasiswa')->restrictOnDelete();
            $table->string('status', 16); // StatusAjuanBeasiswa

            $table->text('motivasi');

            // The GPA when the student applied — the figure the requirement was
            // checked against, which a later term will have moved.
            $table->decimal('ipk_saat_ajuan', 3, 2)->nullable();

            // [{label, path}] in the order the call for applications listed them.
            $table->json('berkas')->nullable();
            $table->timestamp('diajukan_at');

            $table->foreignId('diputus_by_staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->timestamp('diputus_at')->nullable();
            $table->string('catatan_keputusan', 500)->nullable();
            $table->foreignId('beasiswa_penerima_id')->nullable()->constrained('beasiswa_penerima')->restrictOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['beasiswa_periode_id', 'mahasiswa_id'], 'beasiswa_ajuan_periode_mahasiswa_unique');
            $table->index(['mahasiswa_id', 'status'], 'beasiswa_ajuan_mahasiswa_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beasiswa_ajuan');
        Schema::dropIfExists('beasiswa_periode');
    }
};

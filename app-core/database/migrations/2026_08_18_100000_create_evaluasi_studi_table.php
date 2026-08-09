<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Evaluasi studi — the checkpoint where a campus decides whether a student
 * continues.
 *
 * **A finding and a decision are two different columns, and that separation is
 * the point.** The system counts; a person decides. Nothing here ever changes
 * `mahasiswa.status` on its own, because dropping somebody out of their degree
 * is not an outcome a scheduled job may reach unattended.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluasi_studi', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('mahasiswa_id')->constrained('mahasiswa')->restrictOnDelete();

            // The term the evaluation was run *at* — the closed term whose
            // frozen figures it read.
            $table->foreignId('tahun_akademik_id')->constrained('tahun_akademik')->restrictOnDelete();

            /*
             * Which rule fired. Null for the per-term IPS warning, which is not
             * a milestone but a running check.
             */
            $table->string('tahap', 64)->nullable();

            $table->unsignedTinyInteger('semester_ke');

            /*
             * The figures as they stood, copied rather than referenced.
             *
             * A grade correction landing next month must not silently rewrite
             * why somebody was warned. Same reason a BKD report freezes its
             * lines and an issued letter freezes its text.
             */
            $table->unsignedSmallInteger('sks_kumulatif');
            $table->decimal('ipk', 3, 2);
            $table->decimal('ips', 3, 2);

            /*
             * The thresholds in force at the time, frozen alongside the figures.
             *
             * Without these the record cannot be read back: "24 SKS, gagal"
             * makes no sense once the campus lowers its requirement to 20, and
             * nobody can tell whether the old decision was right.
             */
            $table->unsignedSmallInteger('syarat_sks')->nullable();
            $table->decimal('syarat_ipk', 3, 2)->nullable();

            // HasilEvaluasi — what the numbers say.
            $table->string('temuan', 24);

            // KeputusanEvaluasi — what a person decided. Starts as "menunggu".
            $table->string('keputusan', 24)->default('menunggu');

            $table->foreignId('diputuskan_by_staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->timestamp('diputuskan_at')->nullable();
            $table->text('catatan')->nullable();

            $table->timestamps();

            /*
             * One evaluation per student per term per rule.
             *
             * Re-running the sweep must update the pending row rather than pile
             * up duplicates — an operator looking at "3 findings" for the same
             * student cannot tell whether that is three problems or one job run
             * three times.
             */
            $table->unique(['mahasiswa_id', 'tahun_akademik_id', 'tahap'], 'evaluasi_studi_unik');

            $table->index(['tahun_akademik_id', 'keputusan']);
            $table->index(['temuan', 'keputusan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluasi_studi');
    }
};

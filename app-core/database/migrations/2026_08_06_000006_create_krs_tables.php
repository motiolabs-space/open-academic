<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Study plan header, one per student per term.
        Schema::create('krs', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('mahasiswa_id')->constrained('mahasiswa')->restrictOnDelete();
            $table->foreignId('tahun_akademik_id')->constrained('tahun_akademik')->restrictOnDelete();

            $table->unsignedTinyInteger('semester_ke');
            $table->string('status', 16)->default('draft'); // KrsStatus

            $table->unsignedSmallInteger('total_sks')->default(0);

            // Ceiling and the IPS it was derived from, snapshotted at creation
            // so a later grade correction cannot retroactively invalidate an
            // already approved plan.
            $table->unsignedSmallInteger('batas_sks');
            $table->decimal('ips_acuan', 3, 2)->nullable();

            $table->timestamp('diajukan_at')->nullable();
            $table->timestamp('disetujui_at')->nullable();
            $table->foreignId('disetujui_by_dosen_id')->nullable()->constrained('dosen')->nullOnDelete();
            $table->text('catatan_wali')->nullable();

            $table->string('feeder_id', 64)->nullable();
            $table->timestamp('feeder_synced_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['mahasiswa_id', 'tahun_akademik_id']);
            $table->index(['tahun_akademik_id', 'status']);
        });

        // One taken class. Grades hang off this row, which is why it is
        // restrict-on-delete: dropping a plan must never orphan a grade.
        Schema::create('krs_detail', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('krs_id')->constrained('krs')->cascadeOnDelete();
            $table->foreignId('kelas_kuliah_id')->constrained('kelas_kuliah')->restrictOnDelete();

            $table->unsignedTinyInteger('sks');

            // Repeat of a previously failed course: the better grade counts
            // toward the IPK, the attempt is still reported to PDDIKTI.
            $table->boolean('is_mengulang')->default(false);

            // Credits recognised from an MBKM activity rather than a class.
            $table->foreignId('aktivitas_mahasiswa_id')->nullable()
                ->constrained('aktivitas_mahasiswa')->nullOnDelete();

            $table->string('status', 16)->default('diambil'); // diambil|batal

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['krs_id', 'kelas_kuliah_id']);
            $table->index('kelas_kuliah_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('krs_detail');
        Schema::dropIfExists('krs');
    }
};

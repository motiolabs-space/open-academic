<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Weighted assessment components defined per class. Weights must total
        // 100; the service enforces that, the schema only stores it.
        Schema::create('komponen_nilai', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('kelas_kuliah_id')->constrained('kelas_kuliah')->cascadeOnDelete();

            $table->string('nama', 64); // Tugas, UTS, UAS, Praktikum ...
            $table->unsignedTinyInteger('bobot'); // percent
            $table->unsignedTinyInteger('urutan')->default(0);

            $table->timestamps();

            $table->unique(['kelas_kuliah_id', 'nama']);
        });

        Schema::create('nilai_komponen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('komponen_nilai_id')->constrained('komponen_nilai')->cascadeOnDelete();
            $table->foreignId('krs_detail_id')->constrained('krs_detail')->cascadeOnDelete();

            $table->decimal('nilai', 5, 2)->nullable(); // 0.00 - 100.00

            $table->timestamps();

            $table->unique(['komponen_nilai_id', 'krs_detail_id']);
        });

        // Final grade for one taken class. A grade is an event: it is soft
        // deleted and audited, never overwritten in place once finalised.
        Schema::create('nilai', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('krs_detail_id')->unique()->constrained('krs_detail')->cascadeOnDelete();

            // Denormalised for reporting and Feeder payload assembly.
            $table->foreignId('kelas_kuliah_id')->constrained('kelas_kuliah')->restrictOnDelete();
            $table->foreignId('mahasiswa_id')->constrained('mahasiswa')->restrictOnDelete();

            $table->decimal('nilai_angka', 5, 2)->nullable();
            $table->string('nilai_huruf', 2)->nullable(); // GradeLetter
            $table->decimal('bobot', 3, 2)->nullable(); // grade point

            $table->boolean('is_final')->default(false)->index();
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('finalized_by_dosen_id')->nullable()->constrained('dosen')->nullOnDelete();

            // Populated when a finalised grade is corrected through the
            // audited correction path.
            $table->text('catatan_koreksi')->nullable();

            $table->string('feeder_id', 64)->nullable();
            $table->timestamp('feeder_synced_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['mahasiswa_id', 'is_final']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nilai');
        Schema::dropIfExists('nilai_komponen');
        Schema::dropIfExists('komponen_nilai');
    }
};

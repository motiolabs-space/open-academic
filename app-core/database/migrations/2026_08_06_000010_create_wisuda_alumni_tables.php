<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Graduation decision. Verifying it flips the student status to Lulus,
        // which is what Feeder and the student.graduated webhook report.
        Schema::create('yudisium', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('mahasiswa_id')->unique()->constrained('mahasiswa')->restrictOnDelete();
            $table->foreignId('tahun_akademik_id')->constrained('tahun_akademik')->restrictOnDelete();

            $table->string('nomor_sk', 64)->nullable();
            $table->date('tanggal_yudisium')->nullable();

            $table->unsignedSmallInteger('total_sks');
            $table->decimal('ipk', 3, 2);
            $table->string('predikat', 32)->nullable(); // Dengan Pujian, ...

            $table->string('judul_tugas_akhir')->nullable();
            $table->date('tanggal_lulus')->nullable();

            $table->string('status', 24)->default('diajukan'); // diajukan|diverifikasi|ditetapkan

            $table->foreignId('ditetapkan_by_staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->timestamp('ditetapkan_at')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('wisuda_periode', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->string('nama');
            $table->date('tanggal');
            $table->string('lokasi')->nullable();
            $table->unsignedSmallInteger('kuota')->nullable();
            $table->boolean('is_pendaftaran_dibuka')->default(false);

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('wisuda_peserta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wisuda_periode_id')->constrained('wisuda_periode')->cascadeOnDelete();
            $table->foreignId('yudisium_id')->constrained('yudisium')->restrictOnDelete();

            $table->string('nomor_ijazah', 64)->nullable()->unique();
            $table->unsignedSmallInteger('nomor_urut')->nullable();

            $table->timestamps();

            $table->unique(['wisuda_periode_id', 'yudisium_id']);
        });

        // Baseline alumni record. Tracer study and IKU 1 scoring live in Open
        // Campus, which reads this over Campus Bridge — do not grow a tracer
        // questionnaire here.
        Schema::create('alumni', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('mahasiswa_id')->unique()->constrained('mahasiswa')->restrictOnDelete();

            $table->year('tahun_lulus');
            $table->string('email_pribadi')->nullable();
            $table->string('telepon', 32)->nullable();

            $table->string('status_pekerjaan', 48)->nullable(); // bekerja|wirausaha|studi_lanjut|belum
            $table->string('pekerjaan')->nullable();
            $table->string('instansi')->nullable();
            $table->date('mulai_bekerja')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alumni');
        Schema::dropIfExists('wisuda_peserta');
        Schema::dropIfExists('wisuda_periode');
        Schema::dropIfExists('yudisium');
    }
};

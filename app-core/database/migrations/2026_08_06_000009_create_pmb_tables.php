<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Admission wave (gelombang) within an intake term.
        Schema::create('pmb_gelombang', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('tahun_akademik_id')->constrained('tahun_akademik')->restrictOnDelete();

            $table->string('kode', 32)->unique();
            $table->string('nama');
            $table->string('jalur', 32)->default('reguler'); // reguler|prestasi|rpl|transfer

            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');

            $table->unsignedBigInteger('biaya_pendaftaran')->default(0);
            $table->unsignedSmallInteger('kuota')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('pmb_pendaftar', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('pmb_gelombang_id')->constrained('pmb_gelombang')->restrictOnDelete();
            $table->string('nomor_pendaftaran', 32)->unique();

            $table->string('nama');
            $table->string('email');
            $table->string('telepon', 32)->nullable();

            // Collected at registration so the eventual Feeder biodata push
            // is not blocked by a missing national ID.
            $table->string('nik', 32)->nullable();
            $table->string('nisn', 32)->nullable();

            $table->string('tempat_lahir', 64)->nullable();
            $table->date('tanggal_lahir')->nullable();
            $table->char('jenis_kelamin', 1)->nullable();
            $table->string('alamat')->nullable();

            $table->string('asal_sekolah')->nullable();
            $table->year('tahun_lulus_sekolah')->nullable();

            $table->foreignId('prodi_pilihan_1_id')->constrained('prodi')->restrictOnDelete();
            $table->foreignId('prodi_pilihan_2_id')->nullable()->constrained('prodi')->nullOnDelete();
            $table->foreignId('prodi_diterima_id')->nullable()->constrained('prodi')->nullOnDelete();

            $table->string('status', 24)->default('mendaftar')->index(); // ApplicantStatus
            $table->decimal('nilai_seleksi', 5, 2)->nullable();

            // Set once the applicant is converted into a student record.
            $table->foreignId('mahasiswa_id')->nullable()->constrained('mahasiswa')->nullOnDelete();

            $table->text('catatan')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['pmb_gelombang_id', 'status']);
        });

        Schema::create('pmb_berkas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pmb_pendaftar_id')->constrained('pmb_pendaftar')->cascadeOnDelete();

            $table->string('jenis', 48); // ijazah|rapor|kk|ktp|foto|lainnya
            $table->string('nama_file');
            $table->string('file_path');

            $table->boolean('is_verified')->default(false);
            $table->string('catatan')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pmb_berkas');
        Schema::dropIfExists('pmb_pendaftar');
        Schema::dropIfExists('pmb_gelombang');
    }
};

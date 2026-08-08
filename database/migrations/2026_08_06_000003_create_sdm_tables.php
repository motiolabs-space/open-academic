<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Administrative staff (BAAK, keuangan, operator PDDIKTI, pimpinan).
        // Authenticatable on the "staff" guard.
        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->string('nip', 32)->nullable()->unique();
            $table->string('nama');
            $table->string('email')->unique();
            $table->string('password');

            $table->string('telepon', 32)->nullable();
            $table->string('jabatan')->nullable();
            $table->string('unit')->nullable(); // BAAK, Keuangan, Rektorat
            $table->string('foto_path')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();

            $table->timestamps();
            $table->softDeletes();
        });

        // Lecturers. Authenticatable on the "dosen" guard.
        Schema::create('dosen', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            // NIDN/NIDK is the national lecturer number PDDIKTI keys on.
            $table->string('nidn', 32)->nullable()->unique();
            $table->string('nip', 32)->nullable();
            $table->string('nama');
            $table->string('gelar_depan', 32)->nullable();
            $table->string('gelar_belakang', 64)->nullable();

            $table->string('email')->unique();
            $table->string('password');

            $table->string('nik', 32)->nullable();
            $table->string('tempat_lahir', 64)->nullable();
            $table->date('tanggal_lahir')->nullable();
            $table->char('jenis_kelamin', 1)->nullable(); // Gender
            $table->string('agama_kode', 8)->nullable();
            $table->string('telepon', 32)->nullable();
            $table->string('alamat')->nullable();

            // Homebase programme.
            $table->foreignId('prodi_id')->nullable()->constrained('prodi')->nullOnDelete();

            $table->string('jabatan_fungsional', 64)->nullable(); // Asisten Ahli, Lektor, ...
            $table->string('status_kepegawaian', 32)->default('tetap'); // tetap|tidak_tetap|luar_biasa
            $table->string('pendidikan_tertinggi', 8)->nullable(); // EducationLevel

            // A practitioner brought in from industry — the IKU 4 population.
            $table->boolean('is_praktisi')->default(false);
            $table->string('praktisi_instansi')->nullable();

            $table->string('foto_path')->nullable();
            $table->string('feeder_id', 64)->nullable()->index(); // id_dosen from Feeder

            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();

            $table->timestamps();
            $table->softDeletes();
        });

        // Deferred FKs from the master academic tables: both point at dosen,
        // which could not exist before prodi/fakultas were created.
        Schema::table('fakultas', function (Blueprint $table) {
            $table->foreign('dekan_dosen_id')->references('id')->on('dosen')->nullOnDelete();
        });

        Schema::table('prodi', function (Blueprint $table) {
            $table->foreign('kaprodi_dosen_id')->references('id')->on('dosen')->nullOnDelete();
        });

        // External and non-teaching lecturer assignments.
        // Source of truth behind IKU 3 (dosen berkegiatan di luar kampus) and
        // IKU 4 (praktisi mengajar); Open Campus scores them, we only record.
        Schema::create('penugasan_dosen', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('dosen_id')->constrained('dosen')->restrictOnDelete();
            $table->foreignId('tahun_akademik_id')->constrained('tahun_akademik')->restrictOnDelete();

            $table->string('jenis', 48); // LecturerAssignmentType
            $table->string('judul');
            $table->string('mitra_nama')->nullable();
            $table->string('mitra_jenis', 48)->nullable(); // industri|pemerintah|kampus|lsm
            $table->string('lokasi')->nullable();

            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai')->nullable();
            $table->decimal('sks_ekuivalen', 4, 2)->nullable();

            $table->string('dokumen_path')->nullable();
            $table->text('keterangan')->nullable();

            $table->boolean('is_verified')->default(false)->index();
            $table->foreignId('verified_by_staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tahun_akademik_id', 'jenis']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penugasan_dosen');

        Schema::table('prodi', function (Blueprint $table) {
            $table->dropForeign(['kaprodi_dosen_id']);
        });

        Schema::table('fakultas', function (Blueprint $table) {
            $table->dropForeign(['dekan_dosen_id']);
        });

        Schema::dropIfExists('dosen');
        Schema::dropIfExists('staff');
    }
};

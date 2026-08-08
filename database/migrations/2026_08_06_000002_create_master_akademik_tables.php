<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Academic terms. `kode` is the PDDIKTI term code (20261 = 2026/2027
        // Ganjil) and is what every Feeder payload carries.
        Schema::create('tahun_akademik', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->char('kode', 5)->unique();
            $table->year('tahun_mulai');
            $table->char('semester', 1); // SemesterType
            $table->string('nama'); // "2026/2027 Ganjil"

            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');

            // Calendar gates: KRS filling, KRS revision, grade entry.
            $table->date('krs_mulai')->nullable();
            $table->date('krs_selesai')->nullable();
            $table->date('krs_perubahan_selesai')->nullable();
            $table->date('nilai_mulai')->nullable();
            $table->date('nilai_selesai')->nullable();

            $table->boolean('is_active')->default(false)->index();

            // A locked term is closed for any further mutation.
            $table->boolean('is_locked')->default(false);

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('fakultas', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('kode', 16)->unique();
            $table->string('nama');
            $table->string('singkatan', 16)->nullable();

            // FK added in the SDM migration — dosen does not exist yet.
            $table->unsignedBigInteger('dekan_dosen_id')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('prodi', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('fakultas_id')->constrained('fakultas')->restrictOnDelete();

            $table->string('kode', 16)->unique();

            // id_sms from PDDIKTI — the identifier Neo Feeder expects.
            $table->string('kode_pddikti', 64)->nullable()->index();

            $table->string('nama');
            $table->char('jenjang', 2); // EducationLevel
            $table->string('gelar_pendek', 32)->nullable(); // "S.Kom."
            $table->string('gelar_panjang', 128)->nullable();
            $table->string('akreditasi', 16)->nullable();
            $table->unsignedSmallInteger('sks_lulus')->default(144);

            $table->unsignedBigInteger('kaprodi_dosen_id')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();
        });

        // Curricula are versioned: a new version never overwrites the previous
        // one, and a student stays bound to the version they entered under.
        Schema::create('kurikulum', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('prodi_id')->constrained('prodi')->restrictOnDelete();

            $table->string('kode', 32);
            $table->string('nama');
            $table->year('tahun_mulai');
            $table->year('tahun_selesai')->nullable();

            $table->unsignedSmallInteger('sks_wajib')->default(0);
            $table->unsignedSmallInteger('sks_pilihan')->default(0);
            $table->unsignedSmallInteger('sks_lulus')->default(144);

            $table->boolean('is_active')->default(false);

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['prodi_id', 'kode']);
        });

        Schema::create('mata_kuliah', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('prodi_id')->constrained('prodi')->restrictOnDelete();

            $table->string('kode', 32);
            $table->string('nama');
            $table->string('nama_en')->nullable();

            // PDDIKTI reports theory, practice and field-practice credits apart.
            $table->unsignedTinyInteger('sks_teori')->default(0);
            $table->unsignedTinyInteger('sks_praktik')->default(0);
            $table->unsignedTinyInteger('sks_praktik_lapangan')->default(0);
            $table->unsignedTinyInteger('sks')->default(0); // total, kept for queries

            $table->text('deskripsi')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['prodi_id', 'kode']);
        });

        // A course's position and obligation status differ per curriculum
        // version, so they live on the pivot rather than on mata_kuliah.
        Schema::create('kurikulum_mata_kuliah', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kurikulum_id')->constrained('kurikulum')->cascadeOnDelete();
            $table->foreignId('mata_kuliah_id')->constrained('mata_kuliah')->restrictOnDelete();

            $table->unsignedTinyInteger('semester'); // recommended position, 1..14
            $table->string('jenis', 24)->default('wajib'); // wajib|pilihan|wajib_universitas
            $table->timestamps();

            $table->unique(['kurikulum_id', 'mata_kuliah_id']);
        });

        Schema::create('mata_kuliah_prasyarat', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mata_kuliah_id')->constrained('mata_kuliah')->cascadeOnDelete();
            $table->foreignId('prasyarat_id')->constrained('mata_kuliah')->restrictOnDelete();

            // prasyarat: must be passed first. bersamaan: may be taken together.
            $table->string('jenis', 16)->default('prasyarat');
            $table->timestamps();

            $table->unique(['mata_kuliah_id', 'prasyarat_id']);
        });

        Schema::create('gedung', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('kode', 16)->unique();
            $table->string('nama');
            $table->string('alamat')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('ruang', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('gedung_id')->constrained('gedung')->restrictOnDelete();
            $table->string('kode', 32)->unique();
            $table->string('nama');
            $table->unsignedSmallInteger('kapasitas')->default(0);
            $table->string('jenis', 24)->default('kelas'); // kelas|laboratorium|aula
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ruang');
        Schema::dropIfExists('gedung');
        Schema::dropIfExists('mata_kuliah_prasyarat');
        Schema::dropIfExists('kurikulum_mata_kuliah');
        Schema::dropIfExists('mata_kuliah');
        Schema::dropIfExists('kurikulum');
        Schema::dropIfExists('prodi');
        Schema::dropIfExists('fakultas');
        Schema::dropIfExists('tahun_akademik');
    }
};

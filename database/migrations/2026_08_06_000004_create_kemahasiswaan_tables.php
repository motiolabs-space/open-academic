<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Students. Authenticatable on the "mahasiswa" guard, signing in with
        // their NIM. This table is the identity source of truth that Campus
        // Bridge SSO issues tokens against.
        Schema::create('mahasiswa', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->string('nim', 32)->unique();
            $table->string('nama');
            $table->string('email')->unique();
            $table->string('password');

            // Feeder rejects a biodata push without a valid NIK, so the
            // pre-flight validator checks this column before any sync.
            $table->string('nik', 32)->nullable();
            $table->string('nisn', 32)->nullable();

            $table->string('tempat_lahir', 64)->nullable();
            $table->date('tanggal_lahir')->nullable();
            $table->char('jenis_kelamin', 1)->nullable(); // Gender
            $table->string('agama_kode', 8)->nullable();
            $table->string('telepon', 32)->nullable();

            $table->string('alamat')->nullable();
            $table->string('kelurahan', 96)->nullable();
            $table->string('kecamatan', 96)->nullable();
            $table->string('kabupaten', 96)->nullable();
            $table->string('provinsi', 96)->nullable();
            $table->string('kode_pos', 8)->nullable();

            $table->foreignId('prodi_id')->constrained('prodi')->restrictOnDelete();
            $table->foreignId('kurikulum_id')->nullable()->constrained('kurikulum')->nullOnDelete();
            $table->foreignId('dosen_wali_id')->nullable()->constrained('dosen')->nullOnDelete();

            $table->year('angkatan')->index();
            $table->char('term_masuk', 5)->nullable(); // PDDIKTI term code of first enrolment
            $table->string('jalur_masuk', 48)->nullable();

            // Denormalised snapshot of the latest status_mahasiswa row so that
            // student lists do not need a per-row subquery.
            $table->char('status', 1)->default('A')->index(); // StudentStatus

            $table->string('nama_ayah')->nullable();
            $table->string('nama_ibu')->nullable();
            $table->string('pekerjaan_ayah', 64)->nullable();
            $table->string('pekerjaan_ibu', 64)->nullable();
            $table->unsignedBigInteger('penghasilan_ortu')->nullable();

            $table->string('asal_sekolah')->nullable();
            $table->year('tahun_lulus_sekolah')->nullable();

            $table->string('foto_path')->nullable();

            // id_registrasi_mahasiswa returned by Feeder after the first push.
            $table->string('feeder_id', 64)->nullable()->index();
            $table->timestamp('feeder_synced_at')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();

            $table->timestamps();
            $table->softDeletes();
        });

        // Per-term enrolment record. This is the local shape of the PDDIKTI
        // "AktivitasKuliahMahasiswa" payload, and it doubles as the KHS
        // header: once finalised, ips/ipk/sks are frozen for that term.
        Schema::create('status_mahasiswa', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('mahasiswa_id')->constrained('mahasiswa')->restrictOnDelete();
            $table->foreignId('tahun_akademik_id')->constrained('tahun_akademik')->restrictOnDelete();

            $table->char('status', 1); // StudentStatus
            $table->unsignedTinyInteger('semester_ke');

            $table->unsignedSmallInteger('sks_semester')->default(0);
            $table->unsignedSmallInteger('sks_kumulatif')->default(0);
            $table->decimal('ips', 3, 2)->default(0);
            $table->decimal('ipk', 3, 2)->default(0);

            $table->unsignedBigInteger('biaya_kuliah')->nullable();
            $table->string('keterangan')->nullable();

            $table->boolean('is_final')->default(false);
            $table->timestamp('finalized_at')->nullable();

            $table->string('feeder_id', 64)->nullable();
            $table->timestamp('feeder_synced_at')->nullable();

            $table->timestamps();

            $table->unique(['mahasiswa_id', 'tahun_akademik_id']);
            $table->index(['tahun_akademik_id', 'status']);
        });

        Schema::create('cuti_mahasiswa', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('mahasiswa_id')->constrained('mahasiswa')->restrictOnDelete();
            $table->foreignId('tahun_akademik_id')->constrained('tahun_akademik')->restrictOnDelete();

            $table->string('jenis', 32)->default('akademik'); // akademik|sakit|lainnya
            $table->text('alasan');
            $table->string('dokumen_path')->nullable();

            $table->string('status', 32)->default('diajukan'); // LeaveStatus
            $table->timestamp('diajukan_at')->nullable();

            $table->foreignId('diproses_by_staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->timestamp('diproses_at')->nullable();
            $table->text('catatan')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['mahasiswa_id', 'tahun_akademik_id']);
        });

        // MBKM / off-campus activity records — the transactional side of IKU 2.
        // Converted credits land in the KRS as recognised SKS.
        Schema::create('aktivitas_mahasiswa', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('mahasiswa_id')->constrained('mahasiswa')->restrictOnDelete();
            $table->foreignId('tahun_akademik_id')->constrained('tahun_akademik')->restrictOnDelete();
            $table->foreignId('dosen_pembimbing_id')->nullable()->constrained('dosen')->nullOnDelete();

            $table->string('jenis', 48); // StudentActivityType
            $table->string('judul');
            $table->string('mitra_nama')->nullable();
            $table->string('mitra_jenis', 48)->nullable();
            $table->string('lokasi')->nullable();

            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai')->nullable();

            // IKU 2 counts students with at least 20 recognised credits.
            $table->unsignedTinyInteger('sks_konversi')->default(0);

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
        Schema::dropIfExists('aktivitas_mahasiswa');
        Schema::dropIfExists('cuti_mahasiswa');
        Schema::dropIfExists('status_mahasiswa');
        Schema::dropIfExists('mahasiswa');
    }
};

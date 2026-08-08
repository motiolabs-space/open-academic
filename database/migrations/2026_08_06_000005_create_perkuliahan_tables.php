<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A course offering for one term.
        Schema::create('kelas_kuliah', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('tahun_akademik_id')->constrained('tahun_akademik')->restrictOnDelete();
            $table->foreignId('mata_kuliah_id')->constrained('mata_kuliah')->restrictOnDelete();
            $table->foreignId('prodi_id')->constrained('prodi')->restrictOnDelete();

            $table->string('kode', 8); // A, B, C ...
            $table->string('nama')->nullable();

            $table->unsignedSmallInteger('kuota')->default(40);

            // Maintained by the KRS service inside the enrolment transaction;
            // reading it avoids a count() on every catalogue row.
            $table->unsignedSmallInteger('terisi')->default(0);

            $table->unsignedTinyInteger('sks'); // snapshot of the course credits
            $table->string('mode', 16)->default('tatap_muka'); // tatap_muka|daring|hybrid

            // IKU 7 — collaborative and participative teaching methods.
            $table->boolean('is_case_method')->default(false);
            $table->boolean('is_team_based_project')->default(false);

            $table->string('status_nilai', 16)->default('belum'); // belum|sebagian|final
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('finalized_by_dosen_id')->nullable()->constrained('dosen')->nullOnDelete();

            $table->string('feeder_id', 64)->nullable()->index();
            $table->timestamp('feeder_synced_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tahun_akademik_id', 'mata_kuliah_id', 'kode']);
            $table->index(['tahun_akademik_id', 'prodi_id']);
        });

        // Teaching assignments. A row with peran = "praktisi" is what IKU 4
        // counts, and it carries the industry affiliation for evidence.
        Schema::create('kelas_dosen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kelas_kuliah_id')->constrained('kelas_kuliah')->cascadeOnDelete();
            $table->foreignId('dosen_id')->constrained('dosen')->restrictOnDelete();

            $table->string('peran', 24)->default('pengampu'); // pengampu|pendamping|praktisi
            $table->decimal('porsi_sks', 4, 2)->nullable();
            $table->string('praktisi_instansi')->nullable();

            $table->timestamps();

            $table->unique(['kelas_kuliah_id', 'dosen_id']);
        });

        // Weekly recurring slot. Room clashes are detected against this table.
        Schema::create('jadwal_kuliah', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('kelas_kuliah_id')->constrained('kelas_kuliah')->cascadeOnDelete();
            $table->foreignId('ruang_id')->nullable()->constrained('ruang')->nullOnDelete();

            $table->unsignedTinyInteger('hari'); // 1 = Senin ... 7 = Minggu
            $table->time('jam_mulai');
            $table->time('jam_selesai');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['ruang_id', 'hari']);
        });

        // One of the 16 meetings in a term.
        Schema::create('pertemuan_kelas', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('kelas_kuliah_id')->constrained('kelas_kuliah')->cascadeOnDelete();
            $table->foreignId('dosen_id')->nullable()->constrained('dosen')->nullOnDelete();

            $table->unsignedTinyInteger('pertemuan_ke');
            $table->date('tanggal');
            $table->time('jam_mulai')->nullable();
            $table->time('jam_selesai')->nullable();

            $table->string('topik')->nullable();
            $table->string('metode', 16)->default('tatap_muka');
            $table->boolean('is_terlaksana')->default(false);

            // Rotating QR token for self-service attendance; short-lived.
            $table->string('qr_token', 64)->nullable()->unique();
            $table->timestamp('qr_expires_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['kelas_kuliah_id', 'pertemuan_ke']);
        });

        Schema::create('presensi', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('pertemuan_kelas_id')->constrained('pertemuan_kelas')->cascadeOnDelete();
            $table->foreignId('mahasiswa_id')->constrained('mahasiswa')->restrictOnDelete();

            $table->char('status', 1); // AttendanceStatus
            $table->timestamp('waktu_absen')->nullable();
            $table->string('keterangan')->nullable();
            $table->string('sumber', 16)->default('dosen'); // dosen|qr

            $table->timestamps();

            $table->unique(['pertemuan_kelas_id', 'mahasiswa_id']);
            $table->index(['mahasiswa_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presensi');
        Schema::dropIfExists('pertemuan_kelas');
        Schema::dropIfExists('jadwal_kuliah');
        Schema::dropIfExists('kelas_dosen');
        Schema::dropIfExists('kelas_kuliah');
    }
};

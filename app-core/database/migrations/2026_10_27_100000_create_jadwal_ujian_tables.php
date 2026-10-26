<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mid-term and final examination sessions, one per class per exam type.
 *
 * Not the final-project examinations in tugas_akhir_ujian: those examine one
 * student's work before a panel; these seat a whole class, often across several
 * rooms, under invigilators rather than examiners.
 *
 * The term is stored on the session even though the class already carries it.
 * Every reader of this table — the exam card, the portals, and later the event
 * calendar — asks "which sessions fall in this term", and answering through a
 * join on kelas_kuliah for each of them is the kind of thing that ends up
 * written slightly differently in each.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jadwal_ujian', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('kelas_kuliah_id')->constrained('kelas_kuliah')->restrictOnDelete();
            $table->foreignId('tahun_akademik_id')->constrained('tahun_akademik')->restrictOnDelete();

            $table->string('jenis', 16); // JenisUjianKelas
            $table->date('tanggal');
            $table->time('jam_mulai');
            $table->time('jam_selesai');

            $table->string('status', 16)->default('draf'); // StatusJadwalUjian
            $table->timestamp('diterbitkan_at')->nullable();
            $table->string('catatan', 500)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tahun_akademik_id', 'jenis', 'status'], 'jadwal_ujian_term_jenis_status_index');
            $table->index(['tanggal', 'jam_mulai'], 'jadwal_ujian_tanggal_jam_index');
            $table->index(['kelas_kuliah_id', 'jenis'], 'jadwal_ujian_kelas_jenis_index');
        });

        /*
         * The rooms one session is split across, and how many seats each gives.
         *
         * Seats are stored rather than read from the room: a lecture hall of 60
         * seats 30 under exam spacing, and the registrar is the one who knows
         * which halls are used that way.
         */
        Schema::create('jadwal_ujian_ruang', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jadwal_ujian_id')->constrained('jadwal_ujian')->cascadeOnDelete();
            $table->foreignId('ruang_id')->constrained('ruang')->restrictOnDelete();
            $table->unsignedSmallInteger('kursi');
            $table->unsignedTinyInteger('urutan')->default(1);
            $table->timestamps();

            $table->unique(['jadwal_ujian_id', 'ruang_id'], 'jadwal_ujian_ruang_sesi_ruang_unique');
        });

        /*
         * Invigilators: a lecturer or a member of staff, never both on one row.
         *
         * Two nullable foreign keys rather than a morph pair, so the database
         * can still refuse a row pointing at a person who does not exist.
         */
        Schema::create('jadwal_ujian_pengawas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jadwal_ujian_id')->constrained('jadwal_ujian')->cascadeOnDelete();
            $table->foreignId('dosen_id')->nullable()->constrained('dosen')->restrictOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('staff')->restrictOnDelete();

            // The room this invigilator stands in, when the session is split.
            $table->foreignId('jadwal_ujian_ruang_id')->nullable()
                ->constrained('jadwal_ujian_ruang')->nullOnDelete();

            $table->timestamps();

            $table->index('dosen_id', 'jadwal_ujian_pengawas_dosen_index');
            $table->index('staff_id', 'jadwal_ujian_pengawas_staff_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jadwal_ujian_pengawas');
        Schema::dropIfExists('jadwal_ujian_ruang');
        Schema::dropIfExists('jadwal_ujian');
    }
};

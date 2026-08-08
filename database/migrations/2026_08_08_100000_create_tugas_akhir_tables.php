<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The final project — the months between the last taught course and the
 * diploma.
 *
 * Graduation already existed before these tables did, which meant the title
 * printed on a diploma was free text somebody typed at yudisium time. The work
 * itself happened on paper, in WhatsApp groups, and in spreadsheets kept by
 * individual departments; the system only learned the outcome, and only from a
 * keyboard. A number issued on top of retyped facts is a number nobody can
 * check.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tugas_akhir', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('mahasiswa_id')->constrained('mahasiswa')->restrictOnDelete();

            // The term the work was proposed in, not the term it finishes in —
            // a final project routinely spans several.
            $table->foreignId('tahun_akademik_id')->constrained('tahun_akademik')->restrictOnDelete();

            /*
             * One live final project per student, guaranteed by the database.
             *
             * Holds mahasiswa_id while the work is running and NULL once it is
             * finished, rejected, or withdrawn. NULLs do not collide under a
             * unique index on either MySQL or PostgreSQL, so a student's history
             * coexists happily while a second concurrent submission is refused
             * outright.
             *
             * A partial index (WHERE status IN (...)) would say this more
             * directly but is not portable, and a service-level check alone
             * loses to two browser tabs submitting at once.
             */
            $table->foreignId('mahasiswa_aktif_id')->nullable()->unique()
                ->constrained('mahasiswa')->nullOnDelete();

            $table->string('judul', 500);
            $table->text('abstrak')->nullable();
            $table->string('bidang_kajian')->nullable();

            $table->string('status', 16)->index(); // TugasAkhirStatus

            $table->date('tanggal_pengajuan');
            $table->date('tanggal_disetujui')->nullable();
            $table->foreignId('disetujui_by_staff_id')->nullable()->constrained('staff')->nullOnDelete();

            // Rejection reason, or the reason a running project was withdrawn.
            // Read by the student, so it is never optional in the service.
            $table->string('catatan', 500)->nullable();

            // Campuses cap how long a final project may run. Past this date the
            // work is overdue — shown, not auto-cancelled: expiry is a
            // conversation between a student and a department, not a cron job.
            $table->date('batas_selesai')->nullable();

            $table->decimal('nilai_akhir', 5, 2)->nullable();
            $table->string('nilai_huruf', 2)->nullable();
            $table->date('tanggal_selesai')->nullable();

            // Path on the private disk; see BerkasService.
            $table->string('naskah_path')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['mahasiswa_id', 'status']);
        });

        /*
         * Supervisors.
         *
         * A pivot rather than two columns on the parent: campuses run one, two,
         * and occasionally three supervisors, and the roles are not
         * interchangeable. Two columns would have been worked around within a
         * semester by a department that needed a third.
         */
        Schema::create('tugas_akhir_pembimbing', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tugas_akhir_id')->constrained('tugas_akhir')->cascadeOnDelete();
            $table->foreignId('dosen_id')->constrained('dosen')->restrictOnDelete();

            $table->string('peran', 16); // PeranPembimbing
            $table->date('ditetapkan_pada');

            $table->timestamps();

            // One lecturer cannot hold two supervision roles on one project.
            $table->unique(['tugas_akhir_id', 'dosen_id']);

            // Supervision load per lecturer is read on every assignment.
            $table->index('dosen_id');
        });

        /*
         * The consultation log.
         *
         * The record that a final project was actually supervised rather than
         * merely assigned. It is also the evidence a defence is scheduled
         * against, which is why a row does not count until the supervisor signs
         * it off — otherwise the minimum-consultation rule is self-certified by
         * the person it constrains.
         */
        Schema::create('tugas_akhir_bimbingan', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('tugas_akhir_id')->constrained('tugas_akhir')->cascadeOnDelete();
            $table->foreignId('dosen_id')->constrained('dosen')->restrictOnDelete();

            $table->date('tanggal');
            $table->string('topik');
            $table->text('uraian')->nullable();        // written by the student
            $table->text('catatan_dosen')->nullable(); // written by the supervisor

            $table->boolean('disetujui')->default(false);
            $table->timestamp('disetujui_at')->nullable();

            $table->timestamps();

            $table->index(['tugas_akhir_id', 'disetujui']);
            $table->index(['dosen_id', 'disetujui']);
        });

        /*
         * Examinations.
         *
         * Modelled as rows with a jenis rather than as fixed columns on the
         * parent, because campuses genuinely differ: some run one defence, some
         * add a proposal seminar, some add a results seminar as well. Hardcoding
         * three stages would force two-thirds of installations to leave fields
         * blank, and hardcoding one would send the others back to paper.
         */
        Schema::create('tugas_akhir_ujian', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();

            $table->foreignId('tugas_akhir_id')->constrained('tugas_akhir')->cascadeOnDelete();

            $table->string('jenis', 16); // JenisUjian

            $table->date('tanggal');
            $table->time('jam_mulai');
            $table->time('jam_selesai');
            $table->foreignId('ruang_id')->nullable()->constrained('ruang')->nullOnDelete();

            $table->string('status', 16)->index(); // StatusUjian
            $table->string('hasil', 16)->nullable(); // HasilUjian
            $table->decimal('nilai', 5, 2)->nullable();
            $table->date('batas_revisi')->nullable();
            $table->text('catatan')->nullable();
            $table->string('berita_acara_path')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Room double-booking is checked per date.
            $table->index(['tanggal', 'ruang_id']);
        });

        /*
         * The examining panel.
         *
         * Supervisors commonly sit on it — that is normal practice here, so the
         * service does not forbid it. What it does forbid is a panel made up
         * only of supervisors, which is an examination of nobody by nobody.
         */
        Schema::create('tugas_akhir_penguji', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tugas_akhir_ujian_id')->constrained('tugas_akhir_ujian')->cascadeOnDelete();
            $table->foreignId('dosen_id')->constrained('dosen')->restrictOnDelete();

            $table->string('peran', 16); // PeranPenguji
            $table->decimal('nilai', 5, 2)->nullable();
            $table->text('catatan')->nullable();

            $table->timestamps();

            $table->unique(['tugas_akhir_ujian_id', 'dosen_id']);

            // Examiner double-booking is checked across panels.
            $table->index('dosen_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tugas_akhir_penguji');
        Schema::dropIfExists('tugas_akhir_ujian');
        Schema::dropIfExists('tugas_akhir_bimbingan');
        Schema::dropIfExists('tugas_akhir_pembimbing');
        Schema::dropIfExists('tugas_akhir');
    }
};
